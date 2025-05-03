<?php

require_once 'vendor/autoload.php';
use GuzzleHttp\Client;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Shopify API credentials
$shopUrl = 'your_url';
$apiKey = 'your_api_key';

// Base URL for Shopify GraphQL API
$baseUrl = "https://{$shopUrl}/admin/api/2024-10/graphql.json";

// Create a Guzzle HTTP client
$client = new Client([
    'base_uri' => $baseUrl,
    'headers' => [
        'X-Shopify-Access-Token' => $apiKey,
        'Content-Type' => 'application/json'
    ]
]);

function shopifyGraphQL($client, $query)
{
    try {
        $response = $client->request('POST', '', [
            'json' => [
                'query' => $query
            ]
        ]);
        $data = json_decode($response->getBody(), true);
        return $data;
    } catch (Exception $e) {
        echo 'Error: ' . $e->getMessage();
        return null;
    }
}

function getOrders($client, $month, $year)
{
    $startDate = "$year-$month-1";
    // $endDate = "$year-$month-27";
    $endDate = "$year-$month-" . date('t', strtotime($startDate));

    $allOrders = [];
    $hasNextPage = true;
    $cursor = null;

    while($hasNextPage){
        $afterClause = $cursor ? ", after: \"$cursor\"" : '';

        $query = <<<GQL
            query {
                orders(first: 250, query: "created_at:>$startDate AND created_at:<$endDate"$afterClause) {
                    edges {
                        node {
                            id
                            createdAt
                            name
                            email
                            discountCode
                            displayFinancialStatus
                            displayFulfillmentStatus
                            totalDiscounts
                            channel {
                                id
                                name
                            }
                            totalPrice
                            landingPageUrl
                            customerJourneySummary {
                                firstVisit {
                                    landingPage
                                    landingPageHtml
                                    source
                                    utmParameters {
                                        campaign
                                    }
                                }
                            }
                        }
                    }
                    pageInfo {
                        hasNextPage
                        endCursor
                    }
                }
            }
        GQL;

        $response = shopifyGraphQL($client, $query);

        if ($response && isset($response['data']['orders'])) {
            $orders = $response['data']['orders']['edges'] ?? [];
            

            foreach ($orders as $order) {
                $node = $order['node'];
                if (!empty($node['email']) && ($node['displayFinancialStatus']=== 'PAID')) {
                    $allOrders[] = $order;
                }
            }

            $hasNextPage = $response['data']['orders']['pageInfo']['hasNextPage'];
            $cursor = $response['data']['orders']['pageInfo']['endCursor'];
        } else {
            echo "No orders found for the given date range or query.";
            $hasNextPage = false;  
        }
    }
    return $allOrders;
}

$month = 'March_2';
// $month = date("F");
$m = 3;
$year = '2024';

// $allOrders = getOrders($client, date('m') , $year);
$allOrders = getOrders($client, $m , $year);

$count = count($allOrders);
echo "Total Orders with Email and having Paid status: " . $count;

// Optionally, display the filtered orders
echo "<pre>";
print_r($allOrders);
echo "</pre>";



if (count($allOrders) > 0) {


// -------------------------------- INFLUENCERS ---------------------------------------


    $discountArray = [];
    $helloDiscount = [
        "discount_name" => "HELLO",
        "discount_count" => 0,
        "total_price" => 0
    ];
    $clubDiscount = [
        "discount_name" => "CMF",
        "discount_count" => 0,
        "total_price" => 0
    ];

    foreach ($allOrders as $order) {

        
        $discountCode = $order['node']['discountCode'];
        $totalPrice = (float)$order['node']['totalPrice'];


        if (strpos($discountCode, 'CMF') === 0) {
            $clubDiscount['discount_count'] += 1;
            $clubDiscount['total_price'] += $totalPrice;
        } 
        else
        if (strpos($discountCode, 'HELLO') === 0) {
            $helloDiscount['discount_count'] += 1;
            $helloDiscount['total_price'] += $totalPrice;
        } else {
            $discountIndex = array_search($discountCode, array_column($discountArray, 'discount_name'));
            if ($order['node']['discountCode'] > 0) {
                if ($discountIndex === false) {
                    $discountArray[] = [
                        "discount_name" => $discountCode,
                        "discount_count" => 1,
                        "total_price" => $totalPrice
                    ];
                } else {
                    $discountArray[$discountIndex]['discount_count'] += 1;
                    $discountArray[$discountIndex]['total_price'] += $totalPrice;
                }
            } 
            else {
                if ($discountIndex === false) {
                    $discountArray[] = [
                        "discount_name" => $discountCode,
                        "discount_count" => 1,
                        "total_price" => $totalPrice
                    ];
                } else {
                    $discountArray[$discountIndex]['discount_count'] += 1;
                    $discountArray[$discountIndex]['total_price'] += $totalPrice;
                }
            }
        }
    }

    if ($helloDiscount['discount_count'] > 0) {
        $discountArray[] = $helloDiscount;
    }
    if ($clubDiscount['discount_count'] > 0) {
        $discountArray[] = $clubDiscount;
    }
    echo "<table border=1 cellspacing=10 cellpadding=5>";
    echo "<tr><th>Discount Code</th><th>Usage Count</th><th>Total Price</th></tr>";

    foreach ($discountArray as $discount) {
        echo "<tr>";
        if ($discount['discount_name'] == null) {
            echo "<td>" . "*No discount applied*" . "</td>";
        }
        elseif($discount['discount_name'] == 'CMF'){
            echo "<td>" . "CLUB" . "</td>";
        }
         else {
            echo "<td>" . $discount['discount_name'] . "</td>";
        }
        echo "<td>" . $discount['discount_count'] . "</td>";
        echo "<td>" . $discount['total_price'] . "€</td>";
        echo "</tr>";
    }

    echo "</table><br>";

    $discountData = [];
    foreach ($discountArray as $discount) {
        $discountName = $discount['discount_name'];
        
        if ($discountName == null) {
            $discountData[] = [
                "*No discount applied*",
                $discount['discount_count'],
                $discount['total_price'] 
            ];
        } elseif ($discountName == 'CMF') {
            $discountData[] = [
                "CLUB",
                $discount['discount_count'],
                $discount['total_price']
            ];
        } else {
            $discountData[] = [
                $discount['discount_name'],
                $discount['discount_count'],
                $discount['total_price']  
            ];
        }
    }
    

// Google Sheets API Integration
$client = new \Google_Client();
$client->setApplicationName('Google Sheets API');
$client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
$client->setAccessType('offline');
$path = './credentials.json';
$client->setAuthConfig($path);

$service = new \Google_Service_Sheets($client);

$spreadsheetId = 'your_google_sheet'; // Add your google sheet key here

$sheetExists = false;
$spreadsheet = $service->spreadsheets->get($spreadsheetId);
$sheetId = null;

// Check if the sheet corresponding to $month exists and get its sheetId
foreach ($spreadsheet->getSheets() as $sheet) {
    if ($sheet->properties->title == $month) {
        $sheetExists = true;
        $sheetId = $sheet->properties->sheetId;
        break;
    }
}

if (!$sheetExists) {
    $requests = [
        new \Google_Service_Sheets_Request([
            'addSheet' => [
                'properties' => [
                    'title' => $month
                ]
            ]
        ])
    ];

    $batchUpdateRequest = new \Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
        'requests' => $requests
    ]);

    $service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);

    // Re-fetch the spreadsheet to get the newly added sheetId
    $spreadsheet = $service->spreadsheets->get($spreadsheetId);
    foreach ($spreadsheet->getSheets() as $sheet) {
        if ($sheet->properties->title == $month) {
            $sheetId = $sheet->properties->sheetId;
            break;
        }
    }
}

// Combine the header row with the data rows
$headerRow = ["INFLUENCERS/CODIS DESC", "TRANSACCIONES", "Total amb iva(ocult)"];
$range = $month . '!A1';

// Create a ValueRange object and set values, including header
$valueRange = new \Google_Service_Sheets_ValueRange();
$valueRange->setValues(array_merge([$headerRow], $discountData));  // Merge header and data

$options = ['valueInputOption' => 'USER_ENTERED'];
$response = $service->spreadsheets_values->update($spreadsheetId, $range, $valueRange, $options);

// Define the format request to set the background color for the header row
$requests = [
    'updateCells' => [
        'rows' => [
            [
                'values' => [
                    ['userEnteredFormat' => ['backgroundColor' => ['red' => 1]]],  // Red background for the first column
                    ['userEnteredFormat' => ['backgroundColor' => ['red' => 0.485, 'green' => 0.698, 'blue' => 0.879]]], // Blue background for the second column
                    ['userEnteredFormat' => ['backgroundColor' => ['red' => 0.485, 'green' => 0.698, 'blue' => 0.879]]], // Blue background for the third column
                ]
            ]
        ],
        'fields' => 'userEnteredFormat.backgroundColor',
        'range' => [
            'sheetId' => $sheetId,  // Use the correct sheetId for $month
            'startRowIndex' => 0,  // Header row (row 1)
            'endRowIndex' => 1,    // End at the header row
            'startColumnIndex' => 0,
            'endColumnIndex' => 7
        ]
    ]
];

// Execute the batchUpdate request to apply the formatting
$batchUpdateRequest = new \Google_Service_Sheets_BatchUpdateSpreadsheetRequest();
$batchUpdateRequest->setRequests([$requests]);
$service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);


// ------------------------------ META ---------------------------------------


$metaArray = [];
foreach ($allOrders as $order) {
    $meta = $order['node']['customerJourneySummary']['firstVisit']['utmParameters']['campaign'] ?? null;
    $visit_meta = $order['node']['customerJourneySummary']['firstVisit']['source'] ?? null;
    $totalPrice = (float)$order['node']['totalPrice'];
    if ($visit_meta == "Instagram" || $visit_meta == "Facebook" ) {
        $metaIndex = array_search($meta, array_column($metaArray, 'discountmeta_name'));
        
        if($meta){
            // if ($order['node']['discountCode'] > 0) {
            if ($metaIndex === false) {
                $metaArray[] = [
                    "discountmeta_name" => $meta,
                    "discountmeta_count" => 1,
                    "total_price" => $totalPrice
                ];
            } else {
                $metaArray[$metaIndex]['discountmeta_count'] += 1;
                $metaArray[$metaIndex]['total_price'] += $totalPrice;
            }
        }
    // }
        
    }
}
echo "<table border=1 cellspacing=10 cellpadding=5>";
echo "<tr><th>Meta Discounts</th><th>Count</th><th>Total Price</th></tr>";
foreach ($metaArray as $meta) {
    // if($meta['discountmeta_name'] != null){
        echo "<tr>";
    // if ($meta['discountmeta_name'] == null) {
    //     echo "<td>" . "No name available" . "</td>";
    // // } else {
    //     echo "<td>" . $meta['discountmeta_name'] . "</td>";
    // }
    echo "<td>" . $meta['discountmeta_name'] . "</td>";
    echo "<td>" . $meta['discountmeta_count'] . "</td>";
    echo "<td>" . $meta['total_price'] . "€</td>";
    echo "</tr>";
    }
    
// }
echo "</table> <br>";
echo "<br>";

$metaData = [];
foreach ($metaArray as $meta) {
    // Store each discount's details in a row
    // if ($meta['discountmeta_name'] != null) {
        $metaData[] = [
            $meta['discountmeta_name'] ? $meta['discountmeta_name'] : "*No source name*",
            $meta['discountmeta_count'],
            $meta['total_price']
        ];
    // }

}

// Google Sheets API Integration
$client = new \Google_Client();
$client->setApplicationName('Google Sheets API');
$client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
$client->setAccessType('offline');
$path = './credentials.json';
$client->setAuthConfig($path);

$service = new \Google_Service_Sheets($client);

$spreadsheetId = 'your_google_sheet';

$sheetExists = false;
$spreadsheet = $service->spreadsheets->get($spreadsheetId);
$sheetId = null; 

foreach ($spreadsheet->getSheets() as $sheet) {
    if ($sheet->properties->title == $month) {
        $sheetExists = true;
        $sheetId = $sheet->properties->sheetId;
        break;
    }
}

if (!$sheetExists) {
    $requests = [
        new \Google_Service_Sheets_Request([
            'addSheet' => [
                'properties' => [
                    'title' => $month
                ]
            ]
        ])
    ];

    $batchUpdateRequest = new \Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
        'requests' => $requests
    ]);

    $service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);

    $spreadsheet = $service->spreadsheets->get($spreadsheetId);
        foreach ($spreadsheet->getSheets() as $sheet) {
            if ($sheet->properties->title == $month) {
                $sheetId = $sheet->properties->sheetId;
                break;
            }
        }
}

// Combine the header row with the data rows
$headerRow = ["Meta",   "TRANSACCIONES", "Total amb iva(ocult)"];
$range = $month . '!A25';

// Create a ValueRange object and set values, including header
$valueRange = new \Google_Service_Sheets_ValueRange();
$valueRange->setValues(array_merge([$headerRow], $metaData));  // Merge header and data

$options = ['valueInputOption' => 'USER_ENTERED'];
$response = $service->spreadsheets_values->update($spreadsheetId, $range, $valueRange, $options);

// Define the format request to set the background color for the header row
$requests = [
    'updateCells' => [
        'rows' => [
            [
                'values' => [
                    ['userEnteredFormat' => ['backgroundColor' => ['green' => 1]]],  // Red background for the first column
                    ['userEnteredFormat' => ['backgroundColor' => ['red' => 0.485, 'green' => 0.698, 'blue' => 0.879]]], // Blue background for the second column
                    ['userEnteredFormat' => ['backgroundColor' => ['red' => 0.485, 'green' => 0.698, 'blue' => 0.879]]], // Blue background for the third column
                ]
            ]
        ],
        'fields' => 'userEnteredFormat.backgroundColor',
        'range' => [
            'sheetId' => $sheetId,  // Use the correct sheetId for $month
            'startRowIndex' => 24,  // Header row (row 1)
            'endRowIndex' => 25,    // End at the header row
            'startColumnIndex' => 0,
            'endColumnIndex' => 7
        ]
    ]
];

// Execute the batchUpdate request to apply the formatting
$batchUpdateRequest = new \Google_Service_Sheets_BatchUpdateSpreadsheetRequest();
$batchUpdateRequest->setRequests([$requests]);
$service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);


//------------------------  EMAIL  --------------------------


$emailArray = [];
foreach ($allOrders as $order) {
    // $meta = $order['node']['discountCode'];
    $email = $order['node']['customerJourneySummary']['firstVisit']['utmParameters']['campaign'] ?? null;
    $visit_email = $order['node']['customerJourneySummary']['firstVisit']['source'] ?? null;
    $totalPrice = (float)$order['node']['totalPrice'];

    
    if ($visit_email == "email") {
        $emailIndex = array_search($email, array_column($emailArray, 'discountemail_name'));
        // if($meta){
            // if ($order['node']['discountCode'] > 0) {
                if ($emailIndex === false) {
                    $emailArray[] = [
                        "discountemail_name" => $email,
                        "discountemail_count" => 1,
                        "total_price" => $totalPrice
                    ];
                } else {
                    $emailArray[$emailIndex]['discountemail_count'] += 1;
                    $emailArray[$emailIndex]['total_price'] += $totalPrice;
                }
            // }
        // }
        
    }
}
echo "<table border=1 cellspacing=10 cellpadding=5>";
echo "<tr><th>Email Discounts</th><th>Count</th><th>Total Price</th></tr>";
foreach ($emailArray as $email) {
    // if($email['discountemail_name'] !=null){
    echo "<tr>";
    if ($email['discountemail_name'] == null) {
        echo "<td>" . "No name available" . "</td>";
    } else {
        echo "<td>" . $email['discountemail_name'] . "</td>";
    }
    // echo "<td>" . $email['discountemail_name'] . "</td>";
    echo "<td>" . $email['discountemail_count'] . "</td>";
    echo "<td>" . $email['total_price'] . "€</td>";
    echo "</tr>";
    } 
// }
echo "</table> <br>";
echo "<br>";

$emailData = [];
foreach ($emailArray as $email) {
    // if($email['discountemail_name'] != null) {
        $emailData[] = [
            // $email['discountemail_name'],
            $email['discountemail_name'] ? $email['discountemail_name'] : "*No source name*",
            $email['discountemail_count'],
            $email['total_price']
        ];
    // }
    // Store each discount's details in a row
    
}

// Google Sheets API Integration
$client = new \Google_Client();
$client->setApplicationName('Google Sheets API');
$client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
$client->setAccessType('offline');
$path = './credentials.json';
$client->setAuthConfig($path);

$service = new \Google_Service_Sheets($client);

$spreadsheetId = 'your_google_sheet';

$sheetExists = false;
$spreadsheet = $service->spreadsheets->get($spreadsheetId);
$sheetId = null; 

foreach ($spreadsheet->getSheets() as $sheet) {
    if ($sheet->properties->title == $month) {
        $sheetExists = true;
        $sheetId = $sheet->properties->sheetId;
        break;
    }
}

if (!$sheetExists) {
    $requests = [
        new \Google_Service_Sheets_Request([
            'addSheet' => [
                'properties' => [
                    'title' => $month
                ]
            ]
        ])
    ];

    $batchUpdateRequest = new \Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
        'requests' => $requests
    ]);

    $service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);

    $spreadsheet = $service->spreadsheets->get($spreadsheetId);
        foreach ($spreadsheet->getSheets() as $sheet) {
            if ($sheet->properties->title == $month) {
                $sheetId = $sheet->properties->sheetId;
                break;
            }
        }
}
// Combine the header row with the data rows
$headerRow = ["Email",  "TRANSACCIONES", "Total amb iva(ocult)"];
$range = $month . '!A37';

// Create a ValueRange object and set values, including header
$valueRange = new \Google_Service_Sheets_ValueRange();
$valueRange->setValues(array_merge([$headerRow], $emailData));  // Merge header and data

$options = ['valueInputOption' => 'USER_ENTERED'];
$response = $service->spreadsheets_values->update($spreadsheetId, $range, $valueRange, $options);

// Define the format request to set the background color for the header row
$requests = [
    'updateCells' => [
        'rows' => [
            [
                'values' => [
                    ['userEnteredFormat' => ['backgroundColor' => ['red' => 0.485, 'green' => 0.698, 'blue' => 0.879]]],  // Red background for the first column
                    ['userEnteredFormat' => ['backgroundColor' => ['red' => 0.485, 'green' => 0.698, 'blue' => 0.879]]], // Blue background for the second column
                    ['userEnteredFormat' => ['backgroundColor' => ['red' => 0.485, 'green' => 0.698, 'blue' => 0.879]]], // Blue background for the third column
                ]
            ]
        ],
        'fields' => 'userEnteredFormat.backgroundColor',
        'range' => [
            'sheetId' => $sheetId,  // Use the correct sheetId for $month
            'startRowIndex' => 36,  // Header row (row 1)
            'endRowIndex' => 37,    // End at the header row
            'startColumnIndex' => 0,
            'endColumnIndex' => 7
        ]
    ]
];

// Execute the batchUpdate request to apply the formatting
$batchUpdateRequest = new \Google_Service_Sheets_BatchUpdateSpreadsheetRequest();
$batchUpdateRequest->setRequests([$requests]);
$service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);

}