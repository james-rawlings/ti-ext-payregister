<?php namespace SamPoyigi\PayRegister\Payments;

use Admin\Classes\BasePaymentGateway;

class AuthorizeNetAim extends BasePaymentGateway
{
//	public function __construct($controller = null, $params = []) {
//		parent::__construct($controller, $params);
//		$this->load->model('Orders_model');
//		$this->load->model('Addresses_model');
//		$this->load->model('Countries_model');
//
//		$this->load->library('location');                                                        // load the location library
//		$this->location->initialize();
//	}

    public function getAcceptedCards()
    {
        return [
            'visa' => 'lang:sampoyigi.payregister::default.authorize_net_aim.text_visa',
            'mastercard' => 'lang:sampoyigi.payregister::default.authorize_net_aim.text_mastercard',
            'american_express' => 'lang:sampoyigi.payregister::default.authorize_net_aim.text_american_express',
            'jcb' => 'lang:sampoyigi.payregister::default.authorize_net_aim.text_jcb',
            'diners_club' => 'lang:sampoyigi.payregister::default.authorize_net_aim.text_diners_club',
        ];
    }

    public function onRender()
    {
        $this->lang->load('authorize_net_aim/authorize_net_aim');

        $data['code'] = 'authorize_net_aim';
        $data['title'] = $this->setting('title', $data['code']);

        $order_data = $this->session->userdata('order_data');                           // retrieve order details from session userdata
        $data['payment'] = $this->setting('payment', '');
        $data['minimum_order_total'] = is_numeric($this->setting('order_total')) ? $this->setting('order_total') : 0;
        $data['order_total'] = $this->cart->total();

        if (isset($this->input->post['authorize_cc_number'])) {
            $padsize = (strlen($this->input->post['authorize_cc_number']) < 7 ? 0 : strlen($this->input->post['authorize_cc_number']) - 7);
            $data['authorize_cc_number'] = substr($this->input->post['authorize_cc_number'], 0, 4).str_repeat('X', $padsize).substr($this->input->post['authorize_cc_number'], -3);
        }
        else {
            $data['authorize_cc_number'] = '';
        }

        if (isset($this->input->post['authorize_cc_exp_month'])) {
            $data['authorize_cc_exp_month'] = $this->input->post('authorize_cc_exp_month');
        }
        else {
            $data['authorize_cc_exp_month'] = '';
        }

        if (isset($this->input->post['authorize_cc_exp_year'])) {
            $data['authorize_cc_exp_year'] = $this->input->post('authorize_cc_exp_year');
        }
        else {
            $data['authorize_cc_exp_year'] = '';
        }

        if (isset($this->input->post['authorize_cc_cvc'])) {
            $data['authorize_cc_cvc'] = $this->input->post('authorize_cc_cvc');
        }
        else {
            $data['authorize_cc_cvc'] = '';
        }

        if (isset($this->input->post['authorize_country_id'])) {
            $data['authorize_country_id'] = $this->input->post('authorize_country_id');
        }
        else {
            $data['authorize_country_id'] = $this->config->item('country_id');
        }

        $data['order_type'] = $this->location->orderType();

        if ($this->input->post('authorize_address_id')) {
            $data['authorize_address_id'] = $this->input->post('authorize_address_id');                // retrieve existing_address value from $_POST data if set
        }
        else if ($this->customer->getAddressId()) {
            $data['authorize_address_id'] = $this->customer->getAddressId();                                        // retrieve customer default address id from customer library
        }
        else {
            $data['authorize_address_id'] = '';
        }

        if ($this->customer->islogged()) {
            $addresses = $this->Addresses_model->getAddresses($this->customer->getId());                            // retrieve customer addresses array from getAddresses method in Customers model
        }
        else {
            $addresses = [['address_id' => '0', 'address_1' => '', 'address_2' => '', 'city' => '', 'state' => '', 'postcode' => '', 'country_id' => $country_id]];
        }

        $data['addresses'] = [];
        foreach ($addresses as $address) {                                                    // loop through customer addresses arrary
            if (empty($address['country'])) {
                $country = $this->Countries_model->getCountry($address['country_id']);
                $address['country'] = !empty($address['country']) ? $address['country'] : $country['country_name'];
            }

            $data['addresses'][] = [                                                    // create array of address data to pass to view
                'address_id' => $address['address_id'],
                'address_1'  => $address['address_1'],
                'address_2'  => $address['address_2'],
                'city'       => $address['city'],
                'state'      => $address['state'],
                'postcode'   => $address['postcode'],
                'country_id' => $address['country_id'],
                'address'    => str_replace('<br />', ', ', $this->country->addressFormat($address)),
            ];
        }

        $data['countries'] = [];
        $results = $this->Countries_model->isEnabled()->get();                                        // retrieve countries array from getCountries method in locations model
        foreach ($results as $result) {                                                            // loop through crountries array
            $data['countries'][] = [                                                        // create array of countries data to pass to view
                'country_id' => $result['country_id'],
                'name'       => $result['country_name'],
            ];
        }

        // pass array $data and load view files
        $this->load->view('authorize_net_aim/authorize_net_aim', $data);
    }

    public function processPaymentForm($data, $host, $order)
    {
        $this->lang->load('authorize_net_aim/authorize_net_aim');

        $order_data = $this->session->userdata('order_data');                        // retrieve order details from session userdata
        $cart_contents = $this->session->userdata('cart_contents');                                                // retrieve cart contents

        if (empty($order_data) OR empty($cart_contents)) {
            return FALSE;
        }

        $this->form_validation->reset_validation();
        $this->form_validation->set_rules('authorize_cc_number', 'lang:label_card_number', 'required|integer|max:16');
        $this->form_validation->set_rules('authorize_cc_exp_month', 'lang:label_card_expiry', 'required|integer|max:2');
        $this->form_validation->set_rules('authorize_cc_exp_year', 'lang:label_card_expiry', 'required|integer|max:4');
        $this->form_validation->set_rules('authorize_cc_cvc', 'lang:label_card_cvc', 'required|integer|max:4');
        $this->form_validation->set_rules('authorize_same_address', 'lang:label_same_address');

        if ($this->input->post('authorize_same_address') != '1') {
            if ($this->input->post('authorize_address_id') === 'new') {
                $this->form_validation->set_rules('authorize_address_id', 'lang:label_address_id');
                $this->form_validation->set_rules('authorize_address_1', 'lang:label_address_1', 'required|min:3|max:128');
                $this->form_validation->set_rules('authorize_address_2', 'lang:label_address_2', 'max:128');
                $this->form_validation->set_rules('authorize_city', 'lang:label_city', 'required|min:2|max:128');
                $this->form_validation->set_rules('authorize_state', 'lang:label_state', 'max:128');
                $this->form_validation->set_rules('authorize_postcode', 'lang:label_postcode', 'min:2|max:10');
                $this->form_validation->set_rules('authorize_country_id', 'lang:label_country', 'required|integer');
            }
            else {
                $this->form_validation->set_rules('authorize_address_id', 'lang:label_address_id', 'required|integer');
            }
        }

        if ($this->form_validation->run() === TRUE) {                                            // checks if form validation routines ran successfully
            $validated = TRUE;
        }
        else {
            return FALSE;
        }

        if ($validated === TRUE AND !empty($order_data['payment']) AND $order_data['payment'] == 'authorize_net_aim') {    // check if payment method is equal to paypal

            $payment_settings = !empty($order_data['payment_settings']) ? $order_data['payment_settings'] : [];

            if (!empty($payment_settings['order_total']) AND $cart_contents['order_total'] < $payment_settings['order_total']) {
                $this->alert->set('danger', lang('alert_min_total'));

                return FALSE;
            }

            if ($this->validCreditCard($this->input->post('authorize_cc_number'), $payment_settings['accepted_cards']) === FALSE) {
                $accepted_cards = '';
                foreach ($payment_settings['accepted_cards'] as $card_type) {
                    $accepted_cards .= ucwords(str_replace('_', ' ', $card_type)).', ';
                }

                $this->alert->set('danger', sprintf(lang('alert_acceptable_cards'), trim($accepted_cards, ", ")));

                return FALSE;
            }

            $this->load->model('authorize_net_aim/Authorize_net_aim_model');
            $response = $this->Authorize_net_aim_model->authorizeAndCapture($order_data);

            if (isset($response[1], $response[4], $response[8]) AND (int)$response[8] == $order_data['order_id']) {

                if ($response[1] == '2' OR $response[1] == '3') {
                    $order_data['status_id'] = $this->config->item('canceled_order_status');
                }
                else if (isset($payment_settings['order_status']) AND is_numeric($payment_settings['order_status'])) {
                    $order_data['status_id'] = $payment_settings['order_status'];
                }

                $success = FALSE;
                if (($response[1] == '1' OR $response[1] == '4') AND $this->Orders_model->completeOrder($order_data['order_id'], $order_data, $cart_contents)) {
                    $success = TRUE;
                }

                $order_history = [
                    'object_id'  => $order_data['order_id'],
                    'status_id'  => $order_data['status_id'],
                    'notify'     => '0',
                    'comment'    => $this->Authorize_net_aim_model->parseResponse($response),
                    'date_added' => mdate('%Y-%m-%d %H:%i:%s', time()),
                ];

                $this->load->model('Statuses_model');
                $this->Statuses_model->addStatusHistory('order', $order_history);

                if ($success) $this->redirect('checkout/success');                                    // $this->redirect to checkout success page with returned order id

                $this->alert->set('danger', $response[4]);

                return FALSE;
            }
        }
    }

    protected function validCreditCard($number = null, $accepted_cards = [])
    {
        // Credit cards
        $cards = [
            'visa'             => [
                'type'    => 'visa',
                'pattern' => '/^4/',
                'length'  => [13, 16],
            ],
            'mastercard'       => [
                'type'    => 'mastercard',
                'pattern' => '/^(5[0-5]|2[2-7])/',
                'length'  => [16],
            ],
            'american_express' => [
                'type'    => 'american_express',
                'pattern' => '/^3[47]/',
                'format'  => '/(\d{1,4})(\d{1,6})?(\d{1,5})?/',
                'length'  => [15],
            ],
            'diners_club'      => [
                'type'    => 'diners_club',
                'pattern' => '/^3[0689]/',
                'length'  => [14],
            ],
            'jcb'              => [
                'type'    => 'jcb',
                'pattern' => '/^35/',
                'length'  => [16],
            ],
        ];

        $number = preg_replace('/[^0-9]/', '', $number);

        foreach ($cards as $type => $card) {
            if (preg_match($card['pattern'], $number) AND in_array(strlen($number), $card['length'])) {
                return in_array($card['type'], $accepted_cards);
            }
        }

        return FALSE;
    }
}