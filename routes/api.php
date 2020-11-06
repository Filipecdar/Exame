<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});


/* Aqui é realizada a captura das informações da API */
function getFlightData ($args = null) {
    try {
        $url = 'http://prova.123milhas.net/api/flights?' . $args;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);;

        $response = curl_exec ($ch);
        $err = curl_error($ch);
        curl_close ($ch);
        return json_decode($response, true);
    } catch (\Exception $e) {
         echo $e->getMessage();
    }
}

function flightGrouping() {
    $sortedArray = array();
    $groups = array();
    /* Ordenação dos vôos através do índice. 
     Primeiro fator determinante, o tipo de tarefa,
     Segundo fator determinante, se o voo é ida(Outbound) ou volta(Inbound). 
     Terceiro fator determinante, o preço do voo. */
    foreach(getFlightData() as $flight) {
        $travelDirection = $flight['outbound'] ? 'outbound' : 'inbound';
        if (!array_key_exists($flight['fare'], $sortedArray) 
            || !array_key_exists($travelDirection, $sortedArray[$flight['fare']])
            || !array_key_exists($flight['price'], $sortedArray[$flight['fare']][$travelDirection])) {
            $sortedArray[$flight['fare']][$travelDirection][$flight['price']][] = $flight;
        } else {
            array_push($sortedArray[$flight['fare']][$travelDirection][$flight['price']], $flight);
        }
    }

    foreach($sortedArray as $flightsDestination) {
        $anotherDestination = false;
        $firstGrouping = array();
        krsort($flightsDestination);
        foreach ($flightsDestination as $keyDestination => $flightsPrice) {
            foreach($flightsPrice as $keyPrice => $flightFiltered) {
                if (!$anotherDestination) {
                    $firstGrouping[] = array("uniqueId" => uniqid(), "totalPrice" => $keyPrice, $keyDestination => $flightFiltered);
                } else {
                    foreach($firstGrouping as $group){
                        $group[$keyDestination] = $flightFiltered;
                        $group["totalPrice"] += $keyPrice;
                        $groups[] = $group;
                    }
                }
            }
            $anotherDestination = true;
        }
    }

    // Ordenação dos grupos pelo Valor Total.
    $totalPriceColumn = array_column($groups, "totalPrice");
    array_multisort($totalPriceColumn, SORT_ASC, $groups);

    $cheapestPrice = min($totalPriceColumn);
    $cheapestGroup = $groups[array_search(min($totalPriceColumn), $groups)]['uniqueId'];

    return array (
        'cheapestPrice' => $cheapestPrice,
        'cheapestGroup' => $cheapestGroup,
        'groups'        => $groups
    );
}

$this->allFlights = getFlightData();
$groupData = flightGrouping();
$this->groups = $groupData['groups'];
$this->cheapestPrice = $groupData['cheapestPrice'];
$this->cheapestGroup = $groupData['cheapestGroup'];

Route::get('/exame', function() {
    return ['flights'       => $this->allFlights,
            'groups'        => $this->groups,
            'totalGroups'   => count($this->groups),
            'totalFlights'  => count($this->allFlights),
            'cheapestPrice' => $this->cheapestPrice,
            'cheapestGroup' => $this->cheapestGroup ];
});