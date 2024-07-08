<?php

namespace Fleetbase\Storefront\Support;

use Illuminate\Support\Facades\Http;

class MpesaStkpush
{
    protected $consumer_key;
    protected $consumer_secret;
    protected $passkey;
    protected $env;
    protected $short_code;
    protected $callback_url;

    public function __construct($config)
    {
        $this->short_code = $config['short_code'];
        $this->consumer_key = $config['consumer_key'];
        $this->consumer_secret = $config['consumer_secret'];
        $this->passkey = $config['passkey'];
        $this->callback_url = $config['callback_url'];
        $this->env = $config['env']; // 'sandbox' or 'live'
    }

    protected function getAccessToken()
    {
        $access_token_url = ($this->env === 'live') ? 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials' : 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . base64_encode($this->consumer_key . ':' . $this->consumer_secret),
        ])->get($access_token_url);

        return $response->json()['access_token'];
    }

    public function lipaNaMpesa($amount, $phone, $accountReference)
    {
        $timestamp = date('YmdHis');
        $password = base64_encode($this->short_code . $this->passkey . $timestamp);
        $access_token = $this->getAccessToken();

        $stk_push_url = ($this->env === 'live') ? 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest' : 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
        ])->post($stk_push_url, [
            'BusinessShortCode' => $this->short_code,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $phone,
            'PartyB' => $this->short_code,
            'PhoneNumber' => $phone,
            'CallBackURL' => $this->callback_url,
            'AccountReference' => $accountReference,
            'TransactionDesc' => 'Payment for ' . $accountReference,
        ]);

        return $response->json();
    }

    public function status($transactionId)
    {
        $timestamp = date('YmdHis');
        $password = base64_encode($this->short_code . $this->passkey . $timestamp);
        $access_token = $this->getAccessToken();

        $status_url = ($this->env === 'live') ? 'https://api.safaricom.co.ke/mpesa/transactionstatus/v1/query' : 'https://sandbox.safaricom.co.ke/mpesa/transactionstatus/v1/query';

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
        ])->post($status_url, [
            'Initiator' => 'apiop',
            'SecurityCredential' => $password,
            'CommandID' => 'TransactionStatusQuery',
            'TransactionID' => $transactionId,
            'PartyA' => $this->short_code,
            'IdentifierType' => '4',
            'ResultURL' => $this->callback_url,
            'QueueTimeOutURL' => $this->callback_url,
            'Remarks' => 'Transaction status query',
            'Occasion' => 'Transaction status query',
        ]);

        return $response->json();
    }
}
?>
