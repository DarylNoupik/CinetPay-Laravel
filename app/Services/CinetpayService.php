<?php

namespace App\Services;

use CinetPay\CinetPay;
use Exception;

class CinetpayService
{
    protected $cinetpay;
   
    public function __construct()
    {
        $apiKey = env('CINETPAY_API_KEY');
        $siteId = env('CINETPAY_SITE_ID');
        $secretKey = env('CINETPAY_SECRET_KEY');

        $this->cinetpay = new CinetPay($siteId, $apiKey);
    }
  

   /**
     * Génère un lien de paiement
     * @param string $transactionId
     * @param float $amount
     * @param string $currency
     * @param string $description
     * @param array $additionalParams
     * @return array
     * @throws Exception
     */
    public function generatePayment(string $transactionId, float $amount, string $currency = 'XAF', string $description = 'Payment description', array $additionalParams = []): array
    {
        // Préparation des paramètres pour le paiement
        $params = array_merge($additionalParams, [
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'currency' => $currency,
            'description' => $description,
            'invoice_data'=>[],
            'notify_url' => route('payment.notify'),
            'return_url' => route('payment.return'),
            'customer_name' => $additionalParams['customer_name'] ?? 'Nom par défaut',
            'customer_surname' => $additionalParams['customer_surname'] ?? 'Prénom par défaut',
            'channels' => 'ALL',
            "metadata" => "", // utiliser cette variable pour recevoir des informations personnalisés.
            "alternative_currency" => "",//Valeur de la transaction dans une devise alternative
            //Fournir ces variables obligatoirement pour le paiements par carte bancaire
            "customer_email" => "", //l'email du client
            "customer_phone_number" => "", //Le numéro de téléphone du client
            "customer_address" => "", //l'adresse du client
            "customer_city" => "", // ville du client
            "customer_country" => "",//Le pays du client, la valeur à envoyer est le code ISO du pays (code à deux chiffre) ex : CI, BF, US, CA, FR
            "customer_state" => "", //L’état dans de la quel se trouve le client. Cette valeur est obligatoire si le client se trouve au États Unis d’Amérique (US) ou au Canada (CA)
            "customer_zip_code" => "" //Le code postal du client
        ]);
        

        // Appel à la méthode de la classe CinetPay pour générer le lien de paiement
        return $this->cinetpay->generatePaymentLink($params);
    }

    /**
     * Vérifie le statut d'un paiement
     * @param string $transactionId
     * @return array
     * @throws Exception
     */
    public function checkPaymentStatus(string $transactionId): array
    {
        $siteId = $this->$siteId;
        
        if (empty($siteId)) {
            throw new Exception("CINETPAY_SITE_ID est requis pour vérifier le statut du paiement.");
        }

        return $this->cinetpay->getPayStatus($transactionId, $siteId);
    }
}

