<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load dependencies
// require_once 'config.php';
require_once 'menu.php';
require_once 'sms.php';

// Get USSD parameters
$sessionId   = $_POST['sessionId'] ?? '';
$phoneNumber = $_POST['phoneNumber'] ?? '';
$serviceCode = $_POST['serviceCode'] ?? '';
$text        = $_POST['text'] ?? '';

try {
    // Initialize Menu system
    $menu = new Menu($text, $sessionId, $phoneNumber);
    $isRegistered = $menu->isRegistered($phoneNumber);
    
    if($text == "" && !$isRegistered){

        $menu->mainMenuUnregistered();
    }
    elseif(!$isRegistered){
        $textArray=explode("*",$text);
        switch($textArray[0]){
            case 1:
                $menu->menuRegister($textArray);
                break;
            case 2:
                echo "Welcome to Ikaze shop";
                break;
            case 3:
                echo "Contact us on +25507848484";
                break;
            default:
                echo "Invalid Input";
        }
    }
    elseif($text=="" && $isRegistered){
        $menu->mainMenuUser();
    }
// elseif($isRegistered){
//     $textArray = explode('*', $text);
//     $level = count($textArray);

//     switch($textArray[0]){
//         case 1:
//             $menu->productList($textArray, $level);
//             break;
//         case 98:
//             $menu->mainMenuUser();
//             break;
//     }
// }
elseif ($isRegistered) {
    $textArray = explode('*', $text);
    $level = count($textArray);

    // If user enters 98, go to main menu
    if (in_array('98', $textArray)) {
        $menu->mainMenuUser();
         }
    elseif(in_array('0',$textArray)){
      $menu->mainMenuUser();
   
    } 
    else {
        switch ($textArray[0]) {
            case '1':
                $menu->productList($textArray, $level);
                break;
            case 2:
                $menu->Mycart();
                break;
            case 4:
                $menu->CustomerSupport();
                break;
            case 3:
                $menu->Account();
                break;

            
        }
    }
}

    else{
        echo  "Failed";
    }
}
catch(PDOException $e){
   echo "An error occured".$e->getmessage();
}


?>