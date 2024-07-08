<?php
class aamarPay extends PaymentGatewayModule
{
    function __construct()
    {
        $this->name = __CLASS__;

        parent::__construct();
    }

    public function config_fields()
    {
        return [
            "store_id" => [
                "name" => "Store ID",
                "type" => "text",
                "value" => $this->config["settings"]["store_id"] ?? "",
            ],
            "signature_key" => [
                "name" => "Signature Key",
                "type" => "password",
                "value" => $this->config["settings"]["signature_key"] ?? "",
            ],
            "testmode" => [
                "name" => "Test Mode",
                "description" => "",
                "type" => "approval",
                "value" => 1,
                "checked" => (int) ($this->config["settings"]["testmode"] ?? 0),
            ],
        ];
    }

    public function area($params = [])
    {
        $gatewaystore_id = trim($this->config["settings"]["store_id"]);
        $gatewaystore_password = trim($this->config["settings"]["signature_key"]);
        $gatewaybutton_text = $this->lang["pay-button"];
        $gatewaytestmode = $this->config["settings"]["testmode"];
        $randomNumber = time();
        $prefix = strtoupper(substr($gatewaystore_id, 0, 3));
        $tran_id = $prefix . "" . $randomNumber . rand(10000, 99999);

        if ($gatewaytestmode) {
            $url = "https://sandbox.aamarpay.com/jsonpost.php";
        } else {
            $url = "https://secure.aamarpay.com/jsonpost.php";
        }

        $invoiceid = $this->checkout_id;
        $description = "Invoice Payment";
        $amount = $params["amount"]; # Format: ##.##
        $currency = $params["currency"]; # Currency Code
        $product = "Domain - Web Hosting";

        $firstname = $this->clientInfo->name;
        $lastname = $this->clientInfo->surname;
        $email = $this->clientInfo->email;
        $address1 = $this->clientInfo->address->address;
        $address2 = "";
        $city = $this->clientInfo->address->city;
        $state = $this->clientInfo->address->counti;
        $postcode = $this->clientInfo->address->zipcode;
        $country = $this->clientInfo->address->country_code;
        $phone = $this->clientInfo->phone;
        $uuid = $this->clientInfo->id;

        $companyname = __("website/meta/title");
        $systemurl = APP_URI;
        $currency = $params["currency"];
        $returnurl = $this->links["callback"];

        $success_url = $this->links["successful"];
        $fail_url = $this->links["failed"];
        $cancel_url = $this->links["failed"];
        $ipn_url = $this->links["callback"] . "?cid=" . $invoiceid . "&callback_type=ipn";
        $callback_url = $this->links["callback"] . "?cid=" . $invoiceid . "&callback_type=checkout";

        $api_endpoint = $url;

        $main_name = $firstname . " " . $lastname;
        
        $data = [
            "store_id" => $gatewaystore_id,
            "tran_id" => $tran_id,
            "success_url" => $callback_url,
            "fail_url" => $callback_url,
            "cancel_url" => $callback_url,
            "amount" => $amount,
            "currency" => $currency,
            "signature_key" => $gatewaystore_password,
            "desc" => $description,
            "cus_name" => $firstname . " " . $lastname,
            "cus_email" => $email,
            "cus_add1" => $address1,
            "cus_add2" => $address2,
            "cus_city" => $city,
            "cus_state" => $state,
            "cus_postcode" => $postcode,
            "cus_country" => $country,
            "cus_phone" => $phone,
            "type" => "json",
            "opt_a" => $success_url,
            "opt_b" => $fail_url,
            "opt_c" =>$invoiceid
        ];
        
        $json_data = json_encode($data);


        // var_dump($gatewaystore_password);
        // die();

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $json_data,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json"
            ],
        ]);

        $response = curl_exec($curl);

        curl_close($curl);
        
        // var_dump($response);
        // die();

        $responseObj = json_decode($response, true);
        $paymentUrl = $responseObj["payment_url"];
        
        return header('Location: '. $paymentUrl);
        exit();
    }

    public function callback()
    {
        $c_type = Filter::init("GET/callback_type","hclear");
        if($c_type == "ipn") {
            $tran_id = (int) $_POST["mer_txnid"];
            $invoiceid = (int) $_POST["opt_c"];
            $pgid = (int) $_POST["pg_txnid"];
    
            $checkout = $this->get_checkout($invoiceid);
    
            if (!$checkout) {
                $this->error = "Checkout ID unknown";
                return false;
            }
    
            $this->set_checkout($checkout);
    
            $store_id = $this->config["settings"]["store_id"];
            $store_passwd = $this->config["settings"]["signature_key"];
            $gatewaytestmode = $this->config["settings"]["testmode"];
    
            if ($gatewaytestmode) {
                $check_url = "https://sandbox.aamarpay.com";
            } else {
                $check_url = "https://secure.aamarpay.com";
            }
    
            $curl = curl_init();
            $url = $check_url . "/api/v1/trxcheck/request.php?request_id=$tran_id&store_id=$store_id&signature_key=$store_passwd&type=json";
            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
            ]);
    
            $response = curl_exec($curl);
    
            curl_close($curl);
    
            $data = json_decode($response, true);
    
            $status_code = $data["status_code"];
            $pay_status = $data["pay_status"];
    
            if ($pay_status === "Successful") {
                return [
                    "status" => "successful",
                    "message" => [
                        "Merchant Transaction ID" => $tran_id,
                        "Payment gateway Transaction ID" => $_POST["pg_txnid"],
                        "Bank Transaction ID" => $data["bank_trxid"],
                        "Payment type" => $data["payment_type"],
                    ],
                ];
            } else {
                return [
                    "status" => "failed",
                    "message" => [
                        "Merchant Transaction ID" => $tran_id,
                        "Payment gateway Transaction ID" => $_POST["pg_txnid"],
                        "Bank Transaction ID" => $data["bank_trxid"],
                        "Payment type" => $data["payment_type"],
                    ],
                ];
            }
        } else if($c_type == "checkout") {
            $c_id = Filter::init("GET/cid","hclear");
            $invoiceid = $c_id;
            $checkout = $this->get_checkout($invoiceid);
    
            if (!$checkout) {
                $this->error = "Checkout ID unknown";
                return false;
            }
    
            $this->set_checkout($checkout);
    
            $store_id = $this->config["settings"]["store_id"];
            $store_passwd = $this->config["settings"]["signature_key"];
            $gatewaytestmode = $this->config["settings"]["testmode"];
    
            if ($gatewaytestmode) {
                $check_url = "https://sandbox.aamarpay.com";
            } else {
                $check_url = "https://secure.aamarpay.com";
            }
    
            $curl = curl_init();
            $url = $check_url . "/api/v1/trxcheck/request.php?request_id=$invoiceid&store_id=$store_id&signature_key=$store_passwd&type=json";
            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
            ]);
    
            $response = curl_exec($curl);
    
            curl_close($curl);
    
            $data = json_decode($response, true);
            
            // var_dump($data);
            // die();
    
            $status_code = $data["status_code"];
            $pay_status = $data["pay_status"];
    
            if ($pay_status === "Successful") {
                return [
                    "status" => "successful",
                    "message" => [
                        "Merchant Trx. ID" => $invoiceid,
                        "Gateway Trx. ID" => $_POST["pg_txnid"],
                        $data["payment_type"]. " Trx. ID" => $data["bank_trxid"],
                        "Method" => $data["payment_type"],
                        "Amount" => $data["amount_bdt"]
                    ],
                    "redirect" => $data["opt_a"]
                ];
            } else {
                return [
                    "status" => "error",
                    "redirect" => $data["opt_b"]
                ];
            }
            // die();
        }
    }
}
