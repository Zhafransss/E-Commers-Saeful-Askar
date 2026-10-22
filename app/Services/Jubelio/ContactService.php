<?php

namespace App\Services\Jubelio;

use App\Exceptions\JubelioApiException;
use App\Models\Address;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Keeps one Jubelio contact per customer (INT-JB-09): a sales order needs a `contact_id`. The id is
 * stored on the user once, so later checkouts never create another contact.
 */
class ContactService
{
    /** PostgreSQL's code for a foreign key violation, as Jubelio reports it in `code`. */
    private const FOREIGN_KEY_VIOLATION = '23503';

    public function __construct(private readonly JubelioClient $jubelio) {}

    /**
     * The customer's contact id: the stored one, else a contact Jubelio already has for the same email
     * (so a person already known to the shop is not duplicated), else a new one made from this address.
     *
     * Jubelio allows one contact per email or phone. When the number already belongs to a contact the
     * website did not make (the same person bought through another channel, under another email), that
     * contact is used. Its id is kept like any other, since a sales order and a resend from the admin
     * screen both read it from the user; sync() makes sure a profile change never rewrites a contact
     * that is not this customer's.
     */
    public function ensure(User $user, Address $address): int
    {
        if ($user->jubelio_contact_id !== null) {
            return $user->jubelio_contact_id;
        }

        $existing = $this->jubelio->findCustomerByEmail($user->email);

        if ($existing !== null && is_numeric($existing['contact_id'] ?? null)) {
            return $this->remember($user, (int) $existing['contact_id']);
        }

        try {
            return $this->remember($user, $this->save($user, $address, 0));
        } catch (JubelioApiException $e) {
            if (! $this->isDuplicate($e)) {
                throw $e;
            }
        }

        // The email is new to Jubelio, so it is the phone number that is taken.
        $byPhone = filled($user->phone) ? $this->jubelio->findCustomerByPhone($user->phone) : null;

        if ($byPhone !== null && is_numeric($byPhone['contact_id'] ?? null)) {
            return $this->remember($user, (int) $byPhone['contact_id']);
        }

        // Taken, but not findable (a number written another way): make the contact without it.
        return $this->remember($user, $this->jubelio->saveContact($this->payload($user, $address, 0, withPhone: false)));
    }

    /**
     * AUTH-06: pushes a changed name or phone to the existing contact. Nothing to do for a customer who
     * never checked out, since they have no contact yet.
     *
     * Only a contact that carries this customer's own email is updated: one that was matched by phone may
     * belong to someone else (another channel, another email), and must not be renamed from here.
     */
    public function sync(User $user): void
    {
        if ($user->jubelio_contact_id === null) {
            return;
        }

        $own = $this->jubelio->findCustomerByEmail($user->email);

        if ($own === null || (int) ($own['contact_id'] ?? 0) !== $user->jubelio_contact_id) {
            return;
        }

        $this->save($user, $user->defaultAddress, $user->jubelio_contact_id);
    }

    private function remember(User $user, int $id): int
    {
        // Written without mass assignment: the contact id is never set from request input.
        $user->forceFill(['jubelio_contact_id' => $id])->save();

        return $id;
    }

    /**
     * Jubelio reports a taken email or phone as "Email atau phone sudah ada sebelumnya."
     */
    private function isDuplicate(JubelioApiException $e): bool
    {
        return str_contains(mb_strtolower((string) $e->providerMessage), 'sudah ada');
    }

    /**
     * Saves the contact with the address. The address on a contact is only a convenience (the sales order carries
     * its own shipping address), so when Jubelio refuses it because one of its region ids is unknown to the contact
     * table (PostgreSQL 23503, a foreign key violation), the contact is saved again without it instead of failing
     * the checkout.
     */
    private function save(User $user, ?Address $address, int $contactId): int
    {
        try {
            return $this->jubelio->saveContact($this->payload($user, $address, $contactId));
        } catch (JubelioApiException $e) {
            // A duplicate email or phone also comes back as 23503, but removing the address does not help it.
            if ($address === null || $e->providerCode !== self::FOREIGN_KEY_VIOLATION || $this->isDuplicate($e)) {
                throw $e;
            }

            Log::channel('integrations')->warning("jubelio contact of user {$user->id} saved without its address: {$e->detail()}", [
                'province_id' => $address->province_id, 'city_id' => $address->city_id,
                'district_id' => $address->district_id, 'subdistrict_id' => $address->subdistrict_id,
            ]);

            return $this->jubelio->saveContact($this->payload($user, null, $contactId));
        }
    }

    /**
     * Jubelio's live API wants every region id as text (its spec says numbers for some).
     *
     * @return array<string, mixed>
     */
    private function payload(User $user, ?Address $address, int $contactId, bool $withPhone = true): array
    {
        $contact = [
            'contact_id' => $contactId,
            'contact_name' => $user->name,
            'contact_type' => 0,
            'is_company' => false,
            'email' => $user->email,
            'phone' => $withPhone ? $user->phone : null,
            'mobile' => $withPhone ? $user->phone : null,
            'payment_term' => -1,
            'source_detail' => 'Website Saeful Askar',
        ];

        if ($address === null) {
            return $contact;
        }

        return $contact + [
            's_address' => $address->address_line,
            's_province' => $address->province_name,
            's_province_id' => (string) $address->province_id,
            's_city' => $address->city_name,
            's_city_id' => (string) $address->city_id,
            's_district_id' => $address->district_id,
            's_subdistrict' => $address->subdistrict_name,
            's_subdistrict_id' => (string) $address->subdistrict_id,
            's_post_code' => $address->post_code,
        ];
    }
}
