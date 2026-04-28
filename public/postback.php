<?php
// Yieldkit Postback ///////////////////////////////////////////////////////////////////////////////////////////////////

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/services/redirection_functions.php';

/*
{EVENT_ID} The event id, this value is unique.
{ADVERTISER_ID} The advertiser id.
{COMMISSION_ID} The ID of commission, this value can occur multiple times if a commission changes its value or gets cancelled.
{COMMISSION} The commission amount. It is positive for a new commission and negative if the commission was cancelled.
{SALES_DATE} The date when the sale happened.
{MODIFIED_DATE} The date when the event happened.
{SUB_ID} The sub id which you might have specified via yk_tag.
{SALES_AMOUNT} The total amount of the purchase.
{EVENT_TYPE} Can be "NEW" or "UPDATE". Indicates whether it is a new commission in our system or if a commission is updated.
{STATE} The commission status, can be "OPEN", "CONFIRMED", "REJECTED", "DELAYED".
Our S2S Postback only fires commission data in EUR currency (this is why we do not provide a currency parameter there).
*/
//////////////////////////////////////////////////////////////////////////////
$debug = false;
//////////////////////////////////////////////////////////////////////////////
$params = [
    'SUB_ID' => $_GET['SUB_ID'] ?? null,
    'commission_id' => $_GET['COMMISSION_ID'] ?? null,
    'commission' => $_GET['COMMISSION'] ?? null,
    'status' => $_GET['STATE'] ?? null,

    'event_type' => $_GET['EVENT_TYPE'] ?? null,
    'event_id' => $_GET['EVENT_ID'] ?? null,

    'sale_date' => $_GET['SALES_DATE'] ?? null,
    'modified_date' => $_GET['MODIFIED_DATE'] ?? null,

    'advertiser_id' => $_GET['ADVERTISER_ID'] ?? null,
    'sale_amount' => $_GET['SALES_AMOUNT'] ?? null
];

$mustParams = ['SUB_ID', 'commission_id', 'commission', 'status'];
$availableStatuses = ['OPEN', 'CONFIRMED', 'REJECTED', 'PAID'];
//$availableEventTypes = ['NEW', 'UPDATE'];

if ($debug) {
    foreach ($params as $name => $value) {
        if (isset($value) && $value !== '' && $value !== null) {
            echo "Parameter " . ($name) . ": " . $value . "<br>";
        } else {
            echo "Parameter " . ($name) . " is missing.<br>";
        }
    }
} else {
    foreach ($mustParams as $name => $value) {
        if ($value === null) {
            //SEND TO POSTBACK LOG
            exit("ERROR - MISSING PARAMETERS");
        }
    }
}

//DATABASE OPERATIONS
$db = db();

//Validate click_id exists
try {
    $stmt = $db->prepare("SELECT id, created_at FROM clicks WHERE click_id = :click_id");
    $stmt->execute(['click_id' => $params['SUB_ID']]);

    $click = $stmt->fetch();
    if (!$click) {
        if ($debug) {
            echo "ERROR - CLICK NOT FOUND";
            exit();
        } else {
            //LOG ERROR
            exit("ERROR");
        }
    }
} catch (Exception $e) {
        //LOG ERROR
        exit("ERROR - DB ERROR");   
}
//VALIDATE COMMISSION IS A NUMBER
if(!is_numeric($params['commission'])){
    exit("ERROR - INVALID COMMISSION");
}
//VALIDATE STATUS IS IN THE AVAILABLE STATUSES
if(!in_array(strtoupper($params['status']), $availableStatuses)){
    $params['status'] = 'UNKNOWN';
    //LOG ERROR
}
$intStatus = mapStatus($params['status']);

//VALIDATE EVENT TYPE IS IN THE AVAILABLE EVENT TYPES
/*
if(!in_array($params['event_type'], $availableEventTypes)){
    $params['event_type'] = 'UNKNOWN';
    //LOG ERROR
}
*/

//////////////////////////////////////////////////////////////////////////////
//MAYBE CHECK EVENT AND UPDATE OR INSERT /////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////

//INSERT INTO CONVERSION TABLE
try {
    $stmt = $db->prepare("INSERT INTO conversions
        (click_id, commission_id, revenue, status, sale_date, modified_date, event_type, event_id, advertiser_id)
        VALUES (:click_id, :commission_id, :revenue, :status, :sale_date, :modified_date, :event_type, :event_id, :advertiser_id)
        
        ON DUPLICATE KEY UPDATE
                revenue = VALUES(revenue),
                status = VALUES(status),
                event_type = VALUES(event_type),
                event_id = VALUES(event_id),
                modified_date = VALUES(modified_date)
        ");

    $stmt->execute([
        ':click_id' => $click['id'],
        ':commission_id' => $params['commission_id'],
        ':revenue' => $params['commission'],
        ':status' => $intStatus,
        ':event_type' => $params['event_type'],
        ':event_id' => $params['event_id'],
        ':sale_date' => $params['sale_date'],
        ':modified_date' => $params['modified_date'],
        ':advertiser_id' => $params['advertiser_id'],
    ]);
    
} catch (Exception $e) {
    if ($debug) {
        echo "conversion ERROR: " . $e->getMessage();
        exit();
    } else {
        //LOG ERROR
        exit("ERROR - DB ERROR");
    }
}

//GATHER DATA FOR NOTIFICATION ( Names )
try {
    $stmt = $db->prepare("SELECT
        o.name AS offer_name,
        o.id AS offer_id,
        cam.name AS campaign_name,
        cam.id AS campaign_id,
        aa.affiliate_program AS affiliate_name,
        aa.id AS affiliate_id
        FROM clicks c
        LEFT JOIN offers o ON o.id = c.offer_id
        LEFT JOIN campaigns cam ON cam.id = c.campaign_id
        LEFT JOIN affiliate_accounts aa ON aa.id = o.affiliate_program_id
        WHERE click_id = :click_id");

    $stmt->execute([
        ':click_id' => $params['SUB_ID']
    ]);
    $clickDetails = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    if ($debug) {
        echo "GATHER DATA FOR NOTIFICATION ERROR: " . $e->getMessage();
    } else {
        //LOG ERROR
        exit("ERROR - DB ERROR");
    }
}

//INSERT INTO NOTIFICATIONS TABLE
try {
    $stmt = $db->prepare("INSERT INTO notifications
        (click_id,
        offer_id,
        campaign_id,
        affiliate_id,
        revenue,
        status,
        event_type,
        event_id,
        is_read,
        read_at,
        sale_date,
        modified_date
        )

        VALUES (:click_id,
        :offer_id,
        :campaign_id,
        :affiliate_id,
        :revenue,
        :status,
        :event_type,
        :event_id,
        :is_read,
        :read_at,
        :sale_date,
        :modified_date
        )
    ");

    $stmt->execute([
        ':click_id' => $params['SUB_ID'],
        ':offer_id' => $clickDetails['offer_id'], //offer id from click_id
        ':campaign_id' => $clickDetails['campaign_id'], //campaign id from click_id
        ':affiliate_id' => $clickDetails['affiliate_id'], //affiliate id from click_id
        ':revenue' => $params['commission'],
        ':status' => $intStatus,
        ':event_type' => $params['event_type'],
        ':event_id' => $params['event_id'],
        ':is_read' => 0,
        ':read_at' => null,
        ':sale_date' => $params['sale_date'],
        ':modified_date' => $params['modified_date']
    ]);

} catch (Exception $e) {
    if ($debug) {
        echo "NOTIFICATION ERROR: " . $e->getMessage();
        exit();
    } else {
        //LOG ERROR
        exit("ERROR - DB ERROR");
    }
}

//SEND TELEGRAM NOTIFICATION
if(floatval($params['commission']) > 0.1) {

    try {
        $message = "
        <b>| {$params['event_type']} | Conversion💰</b>
        ---------------
        <b>Offer:</b> {$clickDetails['offer_name']}
        <b>Commission:</b> \${$params['commission']}
        <b>Status:</b> {$params['status']}
        <b>Campaign:</b> {$clickDetails['campaign_name']}
        
        <b>Click ID:</b> {$params['SUB_ID']}
        <b>Commission ID:</b> {$params['commission_id']}
        <b>Click Created At:</b> {$click['created_at']}

        ";

        sendTelegramMessage($message);
    } catch (Exception $e) {
        if ($debug) {
            echo "TELEGRAM ERROR: " . $e->getMessage();
            exit();
        } else {
            //LOG ERROR
            exit("ERROR - NOTIFICATION ERROR");
        }
    }
}
/*
else{
    try {
        $message = "Low Commission Update: {$params['commission']} from {$clickDetails['offer_name']}";
        sendTelegramMessage($message);
    } catch (Exception $e) {
        if ($debug) {
            echo "TELEGRAM ERROR: " . $e->getMessage();
            exit();
        } else {
            //LOG ERROR
            exit("ERROR - NOTIFICATION ERROR");
        }
    }
}
    */


echo "\n" . "SUCCESS";
exit();

function mapStatus($status)
{
    switch (strtolower($status)) {
        case 'open':
            return 1;

        case 'confirmed':
            return 2;

        case 'rejected':
            return 3;

        case 'paid':
            return 4;

        default:
            return 0; // unknown / fallback
    }
}   