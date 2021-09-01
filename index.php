<?php

// Errros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


//FatturaElectronica
use Deved\FatturaElettronica\Codifiche\RegimeFiscale;
use Deved\FatturaElettronica\Codifiche\TipoDocumento;
use Deved\FatturaElettronica\FatturaElettronicaFactory;
use Deved\FatturaElettronica\Codifiche\ModalitaPagamento;
use Deved\FatturaElettronica\FatturaElettronica\FatturaElettronicaBody\DatiGenerali;
use Deved\FatturaElettronica\FatturaElettronica\FatturaElettronicaBody\DatiPagamento;
use Deved\FatturaElettronica\FatturaElettronica\FatturaElettronicaHeader\Common\Sede;
use Deved\FatturaElettronica\FatturaElettronica\FatturaElettronicaBody\DatiBeniServizi\Linea;
use Deved\FatturaElettronica\FatturaElettronica\FatturaElettronicaHeader\Common\DatiAnagrafici;
use Deved\FatturaElettronica\FatturaElettronica\FatturaElettronicaBody\DatiBeniServizi\DettaglioLinee;

// Slim
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

// Validation
use Cake\Validation\Validator;


//Vendor
require 'vendor/autoload.php';

// Make App
$app = AppFactory::create();


//Routes 

$app->post('/generate', function (Request $request, Response $response, $args) {

    $formData = (array)$request->getParsedBody();


    $validator = new Validator();

    $validator

    //client info
    ->requirePresence('client_codice_fiscale', 'Client Codice Fiscale is required')
    ->notEmptyString('client_codice_fiscale', 'Client Codice Fiscale is required')

    ->requirePresence('client_name', 'Client name / business name is required ')
    ->notEmptyString('client_name', 'Client name / business name is required ')

    ->requirePresence('client_country_code', 'Client Country code is required')
    ->notEmptyString('client_country_code', 'Client Country code is required')

    ->requirePresence('client_piva', 'Client PIVA is required')
    ->notEmptyString('client_piva', 'Client PIVA is required')

    ->requirePresence('client_pec', 'Client Pec is required')
    ->notEmptyString('client_pec', 'Client Pec is required')

    ->requirePresence('client_sdi', 'Client SDI is required')
    ->notEmptyString('client_sdi', 'Client SDI is required')

    //client address
    ->requirePresence('client_address_country_code', 'Client Address country code is required')
    ->notEmptyString('client_address_country_code', 'Client Address country code is required')

    ->requirePresence('client_address_via', 'Client Address via is required')
    ->notEmptyString('client_address_via', 'Client Address via is required')

    ->requirePresence('client_address_cap', 'Client Address cap is required')
    ->notEmptyString('client_address_cap', 'Client Address cap is required')

    ->requirePresence('client_address_comune', 'Client Address comune is required')
    ->notEmptyString('client_address_comune', 'Client Address comune is required')

    ->requirePresence('client_address_province', 'Client Address province is required')
    ->notEmptyString('client_address_province', 'Client Address province is required')

    //invoice header
    ->requirePresence('invoice_date', 'Invoice Date is required')
    ->notEmptyString('invoice_date', 'Invoice Date is required')

    ->requirePresence('invoice_number', 'Invoice Number is required')
    ->notEmptyString('invoice_number', 'Invoice Number is required')

    ->requirePresence('invoice_total', 'Invoice Total is required')
    ->notEmptyString('invoice_total', 'Invoice Total is required')

    ->requirePresence('invoice_payment_date', 'Invoice payment date is required')
    ->notEmptyString('invoice_payment_date', 'Invoice payment date is required')

    ->requirePresence('invoice_paid_total', 'Invoice paid total is required')
    ->notEmptyString('invoice_paid_total', 'Invoice paid total is required')

    //invoice lines
    ->requirePresence('invoice_lines', 'Invoice lines are required')
    ->isArray('invoice_lines', 'Invoice line must be an array')
    ->notEmptyArray('invoice_lines', 'Invoice line must be an array');

    //show validation errors
    $errors = $validator->validate($formData);

    if ($errors) {

        $response->getBody()->write(json_encode(
            [
                'code'  => 400,
                'meesage' => 'The below fields are required',
                'errors' => $errors
            ]
        ));
        return $response->withHeader('Content-Type', 'application/json');

    }

    //client
    $anagraficaCessionario = new DatiAnagrafici(
        $formData['client_codice_fiscale'],
        $formData['client_name'],
        $formData['client_country_code'],
        $formData['client_piva'],
    );

    //client address
    $sedeCessionario = new Sede(
        $formData['client_address_country_code'], 
        $formData['client_address_via'], 
        $formData['client_address_cap'], 
        $formData['client_address_comune'], 
        $formData['client_address_province']
    );

    // company info
    $anagraficaCedente = new DatiAnagrafici(
        '04763940238', //codiceFiscale
        'AXIS TECNOLOGIES SRLS', //denominazione
        'IT', //idPaese
        '04763940238', //idCodice
        RegimeFiscale::Ordinario //regimeFiscale
    );

    //Our Company Address
    $sedeCedente = new Sede(
        'IT', //Paese
        'VIA VILLABELLA 18', //Indirizzo
        '37047', //Cap
        'SAN BONIFACIO', //Comune
        'VR' //Provincia
    );

    //create fattura factory
    $fatturaElettronicaFactory = new FatturaElettronicaFactory(
        $anagraficaCedente, 
        $sedeCedente, 
        '', 
        ''
    );

    //set customer transfer info
    $fatturaElettronicaFactory->setCessionarioCommittente($anagraficaCessionario, $sedeCessionario, $formData['client_sdi'], $formData['client_pec']);

    //general invoice data
    $datiGenerali = new DatiGenerali(
        TipoDocumento::Fattura,
        $formData['invoice_date'],
        $formData['invoice_number'],
        $formData['invoice_total']
    );

    //payment infomation
    $datiPagamento = new DatiPagamento(
        ModalitaPagamento::Bonifico,
        $formData['invoice_payment_date'],
        $formData['invoice_paid_total']
    );

    // invoice lines
    $linee = [];

    // $linee[] = new Linea('SERVIZIO DI SVILUPPO SOFTWARE', 1000, '', 1);

    foreach($formData['invoice_lines'] as $invoice_line){
        if( 
            (array_key_exists('descrption', $invoice_line) && $invoice_line['descrption'] != "")
             && (array_key_exists('unit_price', $invoice_line) && $invoice_line['unit_price'] != "") 
             && (array_key_exists('qty', $invoice_line) && $invoice_line['qty'] != "")
        ) {
            $linee[] = new Linea($invoice_line['descrption'], $invoice_line['unit_price'], '', $invoice_line['qty']);
        }
        
    }


    $dettaglioLinee = new DettaglioLinee($linee);

    if(!count( $linee)) {
        $response->getBody()->write(json_encode(
            [
                'code'  => 400,
                'meesage' => 'Invoice Lines are missing'
            ]
        ));
        return $response->withHeader('Content-Type', 'application/json');
    }

    
    $fattura = $fatturaElettronicaFactory->create(
        $datiGenerali,
        $datiPagamento,
        $dettaglioLinee,
        '001'
    );

    // File

    $file_name = 'SDI-'.'16'.'.xml';

    // ottenere il nome della fattura conforme per l'SDI
    //$file = $fattura->getFileName();

    //generazione file XML 
    $xml = $fattura->toXml();

    //scrivi file
    file_put_contents($file_name, $xml);

    $response->getBody()->write(json_encode(
        [
            'code'  => 200,
            'meesage' => 'XML created'
        ]
    ));

    return $response->withHeader('Content-Type', 'application/json');

});

$app->run();

