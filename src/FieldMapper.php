<?php
namespace ViaKashmir;

/**
 * Normalises lead form fields from any platform into our unified schema.
 * Works regardless of what field names the vendor uses in their ad forms.
 */
class FieldMapper
{
    // Our field => possible incoming names (first match wins)
    private static array $map = [
        'client_name'     => ['full_name','name','first_name','customer_name','lead_name','firstname','lastname','last_name'],
        'email'           => ['email','email_address','e_mail','emailid'],
        'phone'           => ['phone_number','phone','mobile','mobile_number','contact','whatsapp','contact_number','phonenumber'],
        'destination'     => ['destination','travel_destination','place','location','where_do_you_want_to_travel','travel_place','travel_to'],
        'travel_duration' => ['duration','travel_duration','days','number_of_days','trip_duration','how_many_days','nights'],
        'travel_from_date'=> ['travel_date','start_date','from_date','departure_date','travel_from','check_in'],
        'travel_to_date'  => ['end_date','return_date','to_date','travel_to_date','check_out'],
        'adults'          => ['adults','num_adults','number_of_adults','adult','no_of_adults','pax_adults'],
        'children'        => ['children','kids','num_children','number_of_children','child','no_of_kids','pax_children'],
        'budget'          => ['budget','approx_budget','travel_budget','estimated_budget'],
        'special_requests'=> ['special_requests','comments','notes','requirements','any_specific_requirements','message','additional_info'],
    ];

    /**
     * @param array|string $rawFields  Meta returns [{field, value}] array;
     *                                  Google returns plain key=>value object/array.
     */
    public static function map($rawFields): array
    {
        // Normalise Meta [{field:'x',value:'y'}, ...] format
        $flat = [];
        if (is_array($rawFields)) {
            foreach ($rawFields as $item) {
                if (isset($item['field'], $item['value'])) {
                    $key        = strtolower(preg_replace('/[\s\-]+/', '_', $item['field']));
                    $flat[$key] = $item['value'];
                } elseif (is_string($item)) {
                    // skip
                } else {
                    foreach ($item as $k => $v) {
                        $flat[strtolower(preg_replace('/[\s\-]+/', '_', $k))] = $v;
                    }
                }
            }
        }

        $result = [];
        foreach (self::$map as $ourField => $candidates) {
            foreach ($candidates as $candidate) {
                if (isset($flat[$candidate]) && $flat[$candidate] !== '') {
                    $result[$ourField] = $flat[$candidate];
                    break;
                }
            }
        }

        // Combine first_name + last_name if client_name not found
        if (empty($result['client_name'])) {
            $parts = array_filter([
                $flat['first_name'] ?? '',
                $flat['last_name']  ?? '',
            ]);
            if ($parts) $result['client_name'] = implode(' ', $parts);
        }

        // Coerce numeric fields
        if (isset($result['adults']))          $result['adults']          = (int)$result['adults'];
        if (isset($result['children']))        $result['children']        = (int)$result['children'];
        if (isset($result['travel_duration'])) $result['travel_duration'] = (int)$result['travel_duration'] ?: null;

        return $result;
    }
}
