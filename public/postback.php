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

if ($debug) {
    foreach ($params as $name => $value) {
        if ($value !== null) {
            echo "Parameter " . ($name) . ": " . $value . "<br>";
        } else {
            echo "Parameter " . ($name) . " is missing.<br>";
        }
    }
} else {
    foreach ($params as $name => $value) {
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
    $stmt = $db->prepare("SELECT id FROM clicks WHERE click_id = :click_id");
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
//////////////////////////////////////////////////////////////////////////////
//MAYBE CHECK EVENT AND UPDATE OR INSERT /////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////

//INSERT INTO CONVERSION TABLE
try {
    $stmt = $db->prepare("INSERT INTO conversions
        (click_id, commission_id, revenue, status, sale_date, modified_date, event_type, event_id, advertiser_id)
        VALUES (:click_id, :commission_id, :revenue, :status, :sale_date, :modified_date, :event_type, :event_id, :advertiser_id)
    ");

    $stmt->execute([
        ':click_id' => $click['id'],
        ':commission_id' => $params['commission_id'],
        ':revenue' => $params['commission'],
        ':status' => $params['status'],
        ':event_type' => $params['event_type'],
        ':event_id' => $params['event_id'],
        ':sale_date' => $params['sale_date'],
        ':modified_date' => $params['modified_date'],
        ':advertiser_id' => $params['advertiser_id'],
    ]);
    
} catch (Exception $e) {
    if ($debug) {
        echo "conversion ERROR: " . $e->getMessage();
    } else {
        //LOG ERROR
        exit("ERROR - DB ERROR");
    }
}

//GATHER DATA FOR NOTIFICATION ( Names )
try {
    $stmt = $db->prepare("SELECT
        o.name AS offer_name,
        cam.name AS campaign_name,
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
        click_created_at,
        click_updated_at,
        is_read,
        read_at,
        created_at,
        )

        VALUES (:click_id,
        :offer_id,
        :campaign_id,
        :affiliate_id,
        :revenue,
        :status,
        :event_type,
        :event_id,
        NOW(), -- click created time -> now
        :sale_date,
        :is_read,
        :advertiser_id,
        :sales_amount,
        :modified_date
        )
    ");
/////////////////
//INSERT INTO CONVERSIONS TABLE
////////////////

    $stmt->execute([
        ':click_id' => $params['SUB_ID'],
        ':offer_id' => $clickDetails['offer_name'], //offer name from click_id
        ':campaign_id' => $clickDetails['campaign_name'], //campaign name from click_id
        ':affiliate_id' => $clickDetails['affiliate_id'], //affiliate id from click_id
        ':revenue' => $params['commission'],
        ':status' => $params['status'],
        //click created at
        ':event_type' => $params['event_type'],
        ':event_id' => $params['event_id'],
        ':sale_date' => $params['sale_date'],
        ':is_read' => 0,
        ':advertiser_id' => $params['advertiser_id'],
        ':sales_amount' => $params['sale_amount'],
        ':modified_date' => $params['modified_date']
    ]);

} catch (Exception $e) {
    if ($debug) {
        echo "NOTIFICATION ERROR: " . $e->getMessage();
    } else {
        //LOG ERROR
        exit("ERROR - DB ERROR");
    }
}

//SEND TELEGRAM NOTIFICATION
try {
    $message = "
    <b>{$params['event_type']}💰 Conversion</b>
    ---------------
    <b>Offer:</b> {$clickDetails['offer_name']}
    <b>Commission:</b> \${$params['commission']}
    <b>Status:</b> {$params['status']}
    <b>Campaign:</b> {$clickDetails['campaign_name']}
    ";

    sendTelegramMessage($message);
} catch (Exception $e) {
    if ($debug) {
        echo "TELEGRAM ERROR: " . $e->getMessage();
    } else {
        //LOG ERROR
        exit("ERROR - NOTIFICATION ERROR");
    }
}

