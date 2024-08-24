# Intégration de CinetPay avec Laravel

Ce dépôt propose une intégration complète de CinetPay dans une application Laravel. CinetPay est une plateforme de paiement en ligne qui permet de recevoir des paiements via divers canaux, notamment le mobile money, les cartes bancaires, et d'autres méthodes de paiement électroniques en Afrique.

## Prérequis

-   Laravel 11
-   PHP 8.1 ou supérieur.
-   Composer installé.
-   Compte CinetPay avec les informations d'API (Clé API et Site ID , APP SECRET).

## Installation

### 1. Cloner le projet ou ajouter les fichiers nécessaires

Si vous avez cloné ce projet, vous devriez déjà avoir tous les fichiers. Si vous partez d'une nouvelle application Laravel, suivez les étapes suivantes.

### 2. Ajouter la bibliothèque CinetPay

Vous devez ajouter la bibliothèque CinetPay à votre projet.

-   Creer un repertoire Libraries
    ```sh
    mkdir app/Libraries
    ```
-   Et cloner le sdk-php de cinetpay

    ```sh
    cd app/Libraries

    git clone https://github.com/cobaf/cinetpay-sdk-php
    ```

Ajoutez manuellement la dépendance à votre fichier `composer.json` :

```json
 "autoload": {
        "psr-4": {
            "App\\": "app/",
            "Database\\Factories\\": "database/factories/",
            "Database\\Seeders\\": "database/seeders/",
            "CinetPay\\": "app/Libraries/cinetpay-sdk-php/src/"<=======ici
        }
    },
```

Ensuite, exécutez la commande suivante pour mettre a jour les dépendances :

```bash
composer dump-autoload
```

### 3. Configuration de CinetPay

    Ajoutez vos informations d'API CinetPay dans le fichier .env de votre projet Laravel :

```markdown
CINETPAY_API_KEY=your_cinetpay_api_key
CINETPAY_SITE_ID=your_cinetpay_site_id
CINETPAY_SECRET_KEY=your_cinetpay_secret_key
```

### 4. Créer le service Cinetpay

    Créez un service pour gérer les interactions avec l'API CinetPay :

```php
php artisan make:service CinetpayService
```

Ensuite, ajoutez le code suivant à app/Services/CinetpayService.php :

```php

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

```

### 5. Créer un contrôleur de paiement

Créez un contrôleur pour gérer les paiements :

```bash
php artisan make:controller PaymentController
```

Dans app/Http/Controllers/PaymentController.php, ajoutez le code suivant :

```php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CinetpayService;
use CinetPay\CinetPay;

class PaymentController extends Controller
{
    protected $cinetpayService;

    public function __construct(CinetpayService $cinetpayService)
    {

        $this->cinetpayService = $cinetpayService;
    }

    public function initiatePayment(Request $request)
    {
        $transactionId = uniqid(); // Générer un ID de transaction unique
        $amount = $request->input('amount');
        $description =  'Payment description';




        $response = $this->cinetpayService->generatePayment($transactionId, $amount, 'XAF', $description);
        //dd($response);
        if ($response['code'] !== '201') {
            return back()->with('error', $response['description']);
        }

        return redirect($response["data"]['payment_url']);
    }

    public function notify(Request $request)
    {
       // $transactionId = $request->input('transaction_id');


        if ($request->has('cpm_trans_id')) {
            try {
                // Initialisation des informations nécessaires
                $id_transaction = $request->input('cpm_trans_id');

                // On récupère le statut de la transaction via l'API de CinetPay
                $response = $this->cinetpayService->checkPaymentStatus($transactionId);

                // Extraction des informations retournées
                $amount = $response->chk_amount;
                $currency = $response->chk_currency;
                $message = $response->chk_message;
                $code =  $response->chk_code;
                $metadata = $response->chk_metadata;

                // Enregistrement dans les logs
                $log = "User: ".$request->ip().' - '.now().PHP_EOL.
                       "Code: ".$code.PHP_EOL.
                       "Message: ".$message.PHP_EOL.
                       "Amount: ".$amount.PHP_EOL.
                       "Currency: ".$currency.PHP_EOL.
                       "-------------------------".PHP_EOL;
                \Log::info($log);

                // Vérifiez que le montant payé correspond à celui de votre base de données
                // Ici, vous devriez vérifier que l'id de transaction existe bien dans votre base de données et comparer les montants
                // Par exemple:
                // $order = Order::where('transaction_id', $id_transaction)->first();
                // if (!$order || $order->amount != $amount) { ... }

                if ($code == '00') {
                    // Si le paiement est réussi, mettez à jour le statut de la commande dans la base de données
                    // $order->update(['status' => 'paid']);

                    return response()->json(['success' => 'Félicitations, votre paiement a été effectué avec succès'], 200);
                } else {
                    return response()->json(['error' => 'Échec, votre paiement a échoué pour cause : ' .$message], 400);
                }
            } catch (\Exception $e) {
                \Log::error("Erreur lors du traitement de la notification CinetPay : " . $e->getMessage());
                return response()->json(['error' => "Erreur : " . $e->getMessage()], 500);
            }
        } else {
            return response()->json(['error' => "cpm_trans_id non fourni"], 400);
        }
    }

    public function return(Request $request)
    {
        $transactionId = $request->input('cpm_trans_id');
        $response = $this->cinetpayService->checkPaymentStatus($transactionId);

        if ($response['status'] === 'error' || $response['code'] != '00') {
            return redirect('/')->with('error', 'Payment failed!');
        }

        return redirect('/')->with('success', 'Payment successful!');
    }
}


```

### 6. Configurer les Routes

Ajoutez les routes suivantes à votre fichier routes/web.php :

```php

use App\Http\Controllers\PaymentController;

Route::post('/payment/initiate', [PaymentController::class, 'initiatePayment'])->name('payment.initiate');
Route::post('/payment/notify', [PaymentController::class, 'notify'])->name('payment.notify');
Route::get('/payment/success', [PaymentController::class, 'return'])->name('payment.success');

```

### 7. Tester l'intégration

Maintenant que tout est configuré, vous pouvez tester l'intégration en utilisant les formulaires ou les appels API pour initier un paiement, et voir si les notifications de paiement sont bien gérées.

## Conclusion

Cette intégration vous permet d'accepter des paiements via CinetPay dans votre application Laravel. Assurez-vous de bien tester en environnement sandbox avant de passer en production.

## Ressources

-   Documentation officielle de CinetPay
-   Laravel Documentation

### Explications

-   **Prérequis** : Décrit les exigences minimales pour suivre le guide.
-   **Installation** : Étapes détaillées pour configurer l'intégration, y compris l'installation des dépendances et la configuration de l'application.
-   **Service Cinetpay** : Gère les interactions avec l'API CinetPay.
-   **Contrôleur de paiement** : Gère les demandes de paiement et les notifications.
-   **Routes** : Décrit les routes nécessaires pour initier et recevoir les paiements.
-   **Conclusion** : Finalise le processus et fournit des ressources supplémentaires
