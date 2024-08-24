<?php

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

    // $data = [
    //     'apikey' => $apiKey,
    //     'site_id' => $siteId,
    //     'transaction_id' => uniqid(),
    //     'amount' => $request->input('amount'),
    //     'currency' => 'XAF',
    //     'description' => 'Payment for order',
    //     'return_url' => route('payment.success'),
    //     'notify_url' => route('payment.notify'),
    //     'customer_name' => $request->input('name'),
    //     'customer_email' => $request->input('email'),
    //     'customer_phone_number' => $request->input('phone'),
    //     'customer_address' => $request->input('address'),
    //     'channels' => 'MOBILE_MONEY'
        
    // ];

    // $cinetpay = new CinetPay();

    

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
