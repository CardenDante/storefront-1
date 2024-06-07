<?php

namespace Fleetbase\Storefront\Support;

class MpesaService
{
    protected $merchant_id;
    protected $pass_key;
    protected $consumer_key;
    protected $consumer_secret;

    public function __construct($merchant_id, $pass_key, $consumer_key, $consumer_secret)
    {
        $this->merchant_id = $merchant_id;
        $this->pass_key = $pass_key;
        $this->consumer_key = $consumer_key;
        $this->consumer_secret = $consumer_secret;
    }

    public function initiateSTKPush($customer, $amount, $currency, $checkoutOptions)
    {
        $reference = abs(rand(1000000, 99999999999));
        $msisdn = $customer['phone_number'];
        $reference_one = "TEST_COLLECTION";
        $reference_two = "TEST_COLLECTION";
        $mobile_callback_url = 'http://' . $_SERVER['HTTP_HOST'] . '/mobile_callback_url/' . $reference;
        $timeout_callback_url = 'http://' . $_SERVER['HTTP_HOST'] . '/timeout_callback_url/' . $reference;

        $time_stamp = date("YmdHis", time());
        $msisdn = (int)filter_var($msisdn, FILTER_SANITIZE_NUMBER_INT);
        $password = base64_encode($this->merchant_id . $this->pass_key . $time_stamp);

        // AUTHORIZATION CALL
        $url_register = 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url_register);
        $credentials = base64_encode($this->consumer_key . ':' . $this->consumer_secret);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $credentials)); 
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $curl_response = curl_exec($curl);
        curl_close($curl);

        $response_data = json_decode($curl_response, true);
        if (isset($response_data['access_token'])) {
            $token = $response_data['access_token'];
        } else {
            // Handle error
            throw new \Exception("Unable to retrieve access token");
        }

        // MPESA C2B CALL
        $url = 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'Authorization:Bearer ' . $token)); // setting custom header

        $curl_post_data = array(
            'BusinessShortCode' => $this->merchant_id,
            'Password' => $password,
            'Timestamp' => $time_stamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $msisdn,
            'PartyB' => $this->merchant_id,
            'PhoneNumber' => $msisdn,
            'CallBackURL' => $mobile_callback_url,
            'AccountReference' => $reference_one,
            'TransactionDesc' => $reference_two,
        );

        $data_string = json_encode($curl_post_data);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);

        $curl_response = curl_exec($curl);
        curl_close($curl);

        $json_decoded = json_decode($curl_response, true);

        // Log the response or handle it according to your needs
        if (isset($json_decoded['errorMessage'])) {
            throw new \Exception("M-Pesa Error: " . $json_decoded['errorMessage']);
        }

        return $json_decoded;
    }
}
