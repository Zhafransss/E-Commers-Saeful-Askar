<?php

namespace App\Services\Jubelio;

use App\Models\Address;
use App\Models\User;

/**
 * Keeps one Jubelio contact per customer (INT-JB-09): a sales order needs a `contact_id`. The id is
 * stored on the user once, so later checkouts never create another contact.
 */
class ContactService
{
    public function __construct(private readonly JubelioClient $jubelio) {}

    /**
     * The customer's contact id: the stored one, else a contact Jubelio already has for the same email
     * (so a person already known to the shop is not duplicated), else a new one made from this address.
     */
    public function ensure(User $user, Address $address): int
    {
        if ($user->jubelio_contact_id !== null) {
            return $user->jubelio_contact_id;
        }

        $existing = $this->jubelio->findCustomerByEmail($user->email);
        $id = $existing !== null && is_numeric($existing['contact_id'] ?? null)
            ? (int) $existing['contact_id']
            : $this->jubelio->saveContact($this->payload($user, $address, 0));

        // Written without mass assignment: the contact id is never set from request input.
        $user->forceFill(['jubelio_contact_id' => $id])->save();

        return $id;
    }

    /**
     * AUTH-06: pushes a changed name or phone to the existing contact. Nothing to do for a customer who
     * never checked out, since they have no contact yet.
     */
    public function sync(User $user): void
    {
        if ($user->jubelio_contact_id === null) {
            return;
        }

        $this->jubelio->saveContact($this->payload($user, $user->defaultAddress, $user->jubelio_contact_id));
    }

    /**
     * Jubelio's live API wants every region id as text (its spec says numbers for some).
     *
     * @return array<string, mixed>
     */
    private function payload(User $user, ?Address $address, int $contactId): array
    {
        $contact = [
            'contact_id' => $contactId,
            'contact_name' => $user->name,
            'contact_type' => 0,
            'is_company' => false,
            'email' => $user->email,
            'phone' => $user->phone,
            'mobile' => $user->phone,
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
