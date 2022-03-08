<?php
echo "<pre>";
//var_dump(shell_exec('sudo /opt/bitnami/ctlscript.sh restart php-fpm'));
$customerToken = 'gotbh0o8rcp4w5vws62tbhjerzzq2an0';
$curl = curl_init();

curl_setopt_array($curl, array(
    CURLOPT_URL => "http://45.32.119.200/pub//rest/V1/carts/mine",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_HTTPHEADER => array(
        "authorization: Bearer $customerToken",
        "cache-control: no-cache",
        "content-type: application/json",
        "postman-token: 857dba5c-9c95-d98b-733c-49d53eddfbc9"
    ),
));

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
    echo "cURL Error #:" . $err;
    die(__FILE__.__LINE__);
} else {
    $cartId = $response;
}
var_dump("cartId $cartId");

$curl = curl_init();

curl_setopt_array($curl, array(
    CURLOPT_URL => "http://45.32.119.200/pub//rest/V1/carts/mine/items",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => "{\r\n  \"cartItem\": {\r\n    \"sku\": \"GC 1304 Men Sandal-1\",\r\n    \"qty\": 1,\r\n    \"quote_id\": \"263\",\r\n    \"product_option\": {\r\n      \"extension_attributes\": {\r\n        \"configurable_item_options\": [\r\n          {\r\n            \"option_id\": \"93\",\r\n            \"option_value\": 51\r\n          },\r\n          {\r\n            \"option_id\": \"159\",\r\n            \"option_value\": 218\r\n          },\r\n          {\r\n            \"option_id\": \"161\",\r\n            \"option_value\": 253\r\n          }\r\n        ]\r\n      }\r\n    },\r\n    \"extension_attributes\": {}\r\n  }\r\n}",
    CURLOPT_HTTPHEADER => array(
        "authorization: Bearer $customerToken",
        "cache-control: no-cache",
        "content-type: application/json",
        "postman-token: b0853b27-e702-b463-5311-d3422182ec1e"
    ),
));

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
    echo "cURL Error #:" . $err;
} else {
//    echo $response;
}


$curl = curl_init();

curl_setopt_array($curl, array(
    CURLOPT_URL => "http://45.32.119.200/pub//rest/default/V1/carts/mine/estimate-shipping-methods-by-address-id",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => "{\"addressId\": \"829\"}",
    CURLOPT_HTTPHEADER => array(
        "authorization: Bearer $customerToken",
        "cache-control: no-cache",
        "content-type: application/json",
        "postman-token: 787cfb59-99ac-3c72-19ee-be1bc49b95fb"
    ),
));

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
    echo "cURL Error #:" . $err;
} else {
//    echo $response;
}


$curl = curl_init();

curl_setopt_array($curl, array(
    CURLOPT_URL => "http://45.32.119.200/pub//rest/default/V1/carts/mine/shipping-information",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => "{\n  \"addressInformation\": {\n    \"shipping_address\": {\n      \"customerAddressId\": \"829\",\n      \"countryId\": \"IN\",\n      \"regionId\": \"599\",\n      \"regionCode\": \"TN\",\n      \"region\": \"Tamil Nadu\",\n      \"customerId\": \"196\",\n      \"street\": [\n        \"15\",\n        \"\",\n        \"New India street\"\n      ],\n      \"company\": \"SKT buyer\",\n      \"telephone\": \"1142480246\",\n      \"fax\": null,\n      \"postcode\": \"600040\",\n      \"city\": \"Chennai\",\n      \"firstname\": \"SKT Buyer\",\n      \"lastname\": \"XYZ\",\n      \"middlename\": null,\n      \"prefix\": null,\n      \"suffix\": null,\n      \"vatId\": null,\n      \"customAttributes\": []\n    },\n    \"billing_address\": {\n      \"customerAddressId\": \"829\",\n      \"countryId\": \"IN\",\n      \"regionId\": \"599\",\n      \"regionCode\": \"TN\",\n      \"region\": \"Tamil Nadu\",\n      \"customerId\": \"196\",\n      \"street\": [\n        \"15\",\n        \"\",\n        \"New India street\"\n      ],\n      \"company\": \"SKT buyer\",\n      \"telephone\": \"1142480246\",\n      \"fax\": null,\n      \"postcode\": \"600040\",\n      \"city\": \"Chennai\",\n      \"firstname\": \"SKT Buyer\",\n      \"lastname\": \"XYZ\",\n      \"middlename\": null,\n      \"prefix\": null,\n      \"suffix\": null,\n      \"vatId\": null,\n      \"customAttributes\": [],\n      \"saveInAddressBook\": null\n    },\n    \"shipping_method_code\": \"mpmultishipping\",\n    \"shipping_carrier_code\": \"mpmultishipping\",\n    \"extension_attributes\": {\n      \"selected_shipping\": \"[{\\\"sellerid\\\":197,\\\"itemid\\\":\\\"327\\\",\\\"price\\\":149,\\\"baseamount\\\":149,\\\"code\\\":\\\"webkulshipping_22\\\",\\\"method\\\":\\\"SriSeller Shipping (Table Rate)\\\"}]\",\n      \"multi_customship\": 149\n    }\n  }\n}",
    CURLOPT_HTTPHEADER => array(
        "authorization: Bearer $customerToken",
        "cache-control: no-cache",
        "content-type: application/json",
        "postman-token: 36ad1d8f-6ba1-e3b1-f662-66994c378021"
    ),
));

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
    echo "cURL Error #:" . $err;
} else {
//    echo $response;
}
print_r(json_decode($response, true));die;

$curl = curl_init();

curl_setopt_array($curl, array(
    CURLOPT_URL => "http://45.32.119.200/pub//rest/default/V1/carts/mine/set-payment-information",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => "{\"cartId\":\"$cartId\",\"paymentMethod\":{\"method\":\"checkmo\"}}",
    CURLOPT_HTTPHEADER => array(
        "authorization: Bearer $customerToken",
        "cache-control: no-cache",
        "content-type: application/json",
        "postman-token: 97e820ab-ea19-2d55-44f2-cd74dd5c79a6"
    ),
));

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
    echo "cURL Error #:" . $err;
} else {
//    echo $response;
}

print_r(json_decode($response, true));
die;

$curl = curl_init();

curl_setopt_array($curl, array(
    CURLOPT_URL => "http://45.32.119.200/pub//rest/default/V1/carts/mine/payment-information",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => "{\n  \"cartId\": \"$cartId\",\n  \"billingAddress\": {\"customerAddressId\":\"829\",\"countryId\":\"IN\",\"regionId\":\"599\",\"regionCode\":\"TN\",\"region\":\"Tamil Nadu\",\"customerId\":\"196\",\"street\":[\"15\",\"\",\"New India street\"],\"company\":\"SKT buyer\",\"telephone\":\"1142480246\",\"fax\":null,\"postcode\":\"600040\",\"city\":\"Chennai\",\"firstname\":\"SKT Buyer\",\"lastname\":\"XYZ\",\"middlename\":null,\"prefix\":null,\"suffix\":null,\"vatId\":null,\"customAttributes\":[],\"saveInAddressBook\":null},\n  \"paymentMethod\": {\n    \"method\": \"checkmo\",\n    \"po_number\": null,\n    \"additional_data\": null\n  }\n}",
    CURLOPT_HTTPHEADER => array(
        "authorization: Bearer $customerToken",
        "cache-control: no-cache",
        "content-type: application/json",
        "postman-token: 6a9be162-147a-ade9-a867-879401ec3a81"
    ),
));

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
    echo "cURL Error #:" . $err;
} else {
    echo $response;
}
