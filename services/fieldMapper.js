'use strict';
/**
 * Normalises raw lead form fields from Meta / Google into our unified schema.
 * Works with ANY field naming convention vendors use.
 */

// Map of our fields → possible incoming names (priority: first match wins)
const FIELD_MAP = {
  client_name:     ['full_name', 'name', 'first_name', 'customer_name', 'lead_name',
                    'firstname', 'last_name', 'lastname'],
  email:           ['email', 'email_address', 'e_mail', 'emailid'],
  phone:           ['phone_number', 'phone', 'mobile', 'mobile_number', 'contact',
                    'whatsapp', 'contact_number', 'phonenumber'],
  destination:     ['destination', 'travel_destination', 'place', 'location',
                    'where_do_you_want_to_travel', 'travel_place', 'travel_to'],
  travel_duration: ['duration', 'travel_duration', 'days', 'number_of_days',
                    'trip_duration', 'how_many_days', 'nights'],
  travel_from_date:['travel_date', 'start_date', 'from_date', 'departure_date',
                    'travel_from', 'check_in'],
  travel_to_date:  ['end_date', 'return_date', 'to_date', 'travel_to_date', 'check_out'],
  adults:          ['adults', 'num_adults', 'number_of_adults', 'adult',
                    'no_of_adults', 'pax_adults'],
  children:        ['children', 'kids', 'num_children', 'number_of_children',
                    'child', 'no_of_kids', 'pax_children'],
  budget:          ['budget', 'approx_budget', 'travel_budget', 'estimated_budget'],
  special_requests:['special_requests', 'comments', 'notes', 'requirements',
                    'any_specific_requirements', 'message', 'additional_info'],
};

/**
 * @param {Object|Array} rawFields  – Meta returns array [{field, value}];
 *                                    Google returns plain object.
 */
function mapFields(rawFields) {
  // Normalise Meta array format → plain object
  let flat = {};
  if (Array.isArray(rawFields)) {
    rawFields.forEach(({ field, value }) => {
      flat[field.toLowerCase().replace(/[\s-]/g, '_')] = value;
    });
  } else if (rawFields && typeof rawFields === 'object') {
    Object.entries(rawFields).forEach(([k, v]) => {
      flat[k.toLowerCase().replace(/[\s-]/g, '_')] = v;
    });
  }

  const result = {};
  for (const [ourField, candidates] of Object.entries(FIELD_MAP)) {
    for (const candidate of candidates) {
      if (flat[candidate] !== undefined && flat[candidate] !== '') {
        result[ourField] = flat[candidate];
        break;
      }
    }
  }

  // Coerce numeric fields
  if (result.adults)          result.adults          = parseInt(result.adults, 10)  || 0;
  if (result.children)        result.children        = parseInt(result.children, 10)|| 0;
  if (result.travel_duration) result.travel_duration = parseInt(result.travel_duration, 10) || null;

  // If first_name + last_name come separately, combine
  if (!result.client_name && flat.first_name) {
    result.client_name = [flat.first_name, flat.last_name].filter(Boolean).join(' ');
  }

  return result;
}

module.exports = { mapFields };
