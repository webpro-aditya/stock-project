<?php


if (!function_exists('demoResponse')) {
    function demoResponse()
    {
        $response = [
            "memberCode" => "90377",
            "responseCode" => [
                "601"
            ],
            "loginId" => "Dion",
            "token" => "eyJhbGciOiJSUzI1NiJ9.eyJtZW1iZXJDZCI6IjkwMzc3Iiwic3ViIjoiOTAzNzciLCJsb2dpbklkIjoiRGlvbiIsImlzcyI6IkRpb24iLCJleHAiOjE3NzAyNzI0MTEsImlhdCI6MTc3MDI2ODgxMSwianRpIjoiZDdmN2M3OGQtY2Y5Ni00MWRjLTlmM2EtMjU5MjA1MDkyMTRmIn0.IrDdULupM2hzn5e68PLUPApi7_J1lomOJ5Vpd7MA0ehk3hrhyL-89G-OEP-_7pRlqGl69kh74PtpFhKqZ_GTNw",
            "status" => "success"
        ];

        return $response;
    }
}
