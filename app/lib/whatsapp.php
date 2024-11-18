<?php

class WhatsAppAPIClient
{
    private $apiUrl;

    public function __construct($apiUrl)
    {
        $this->apiUrl = $apiUrl;
    }

    public function sendMessage($phone, $name, $message)
    {
        $data = [
            'phone' => $phone,
            'name' => $name,
            'message' => $message
        ];

        // Inicializa cURL
        $ch = curl_init($this->apiUrl);

        // Configura las opciones de cURL
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        // Ejecuta la solicitud y obtiene la respuesta
        $response = curl_exec($ch);

        // Verifica si hay errores en la solicitud
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            curl_close($ch);
            throw new Exception('Error en la solicitud cURL: ' . $error_msg);
        }

        // Cierra la conexión cURL
        curl_close($ch);

        // Decodifica la respuesta JSON
        $responseData = json_decode($response, true);

        // Retorna la respuesta
        return $responseData;
    }
}
