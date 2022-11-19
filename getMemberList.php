<?php
include 'vendor/autoload.php';

use JonnyW\PhantomJs\Client;

function getRegionIdList()
{
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://bniamerica.com/web/open/appsCmsNationalMemberSearchFilterJson?&request_locale=en_US&siteLocale=en&siteLocaleCountry=US',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => 'countryId=10&init=true',
        CURLOPT_HTTPHEADER => array(
            ': authority: bniamerica.com',
            ': method: POST',
            ': path: /web/open/appsCmsNationalMemberSearchFilterJson?&request_locale=en_US&siteLocale=en&siteLocaleCountry=US',
            ': scheme: https',
            'accept:  application/json, text/javascript, */*; q=0.01',
            'accept-encoding:  gzip, deflate',
            'accept-language:  en-US,en;q=0.9',
            'content-type:  application/x-www-form-urlencoded; charset=UTF-8',
            'origin:  https://bniamerica.com',
            'referer:  https://bniamerica.com/en-US/findamember',
            'sec-fetch-dest:  empty',
            'sec-fetch-mode:  cors',
            'sec-fetch-site:  same-origin',
            'user-agent:  Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.141 Safari/537.36',
            'x-requested-with:  XMLHttpRequest',
            'Cookie: JSESSIONID=B851EC162786B5D8D145229E9281E5E4; __cfduid=dd8ac33c403156369d6623d545ddbc2041614359132'
        ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);
    $res = json_decode($response);
    return $res->regions;
}

function getBNIData($region,$company,$keywords)
{

    $client = Client::getInstance();
    $client->getEngine()->setPath('c:/Users/win10/Documents/Upwork/JeffreyKelly/bin/phantomjs.exe');
    $request = $client->getMessageFactory()->createRequest();
    $response = $client->getMessageFactory()->createResponse();
    $x=0;
    $request->setMethod("GET");
    $request->setTimeout(12000);
    $request->setUrl('https://bniamerica.com/en-US/memberlist?countryIds=10&regionId='.$region.
        '&chapterName=&chapterArea=&memberKeywords='.$keywords.
        '&chapterCity=&memberFirstName=&memberLastName=&memberCompany='.$company);
    do {
        $request->setDelay(3+$x);
        $x++;
        $client->send($request, $response);
    } while (stripos($response->getContent(),"memberListTable")===false);

    return $response->getContent();
}

function parseHTML($html){
    $result=array();
    try {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_use_internal_errors(false);
        $table = $dom->getElementById("memberListTable");
        $trs = $table->getElementsByTagName("tr");
        $i = 0;
        foreach ($trs as $row) {
            $tds = $row->getElementsByTagName("td");
            if (count($tds) > 1) {
                $membername = $tds[0]->nodeValue;
                $names = preg_split('/ /', $membername);
                if (count($names) > 1) {
                    $lastname = $names[count($names) - 1];
                    $firstname = implode(" ", array_slice($names, 0, -1));
                } else {
                    $firstname = $names[0];
                    $lastname = "";
                }
                $profile="";
                if (count($tds[0]->getElementsByTagName("a"))>0) {
                    $profile = $tds[0]->getElementsByTagName("a")[0]->getAttribute("href");
                }

                $result[$i][0] = $firstname;
                $result[$i][1] = $lastname;
                $result[$i][2] = 'https://bniamerica.com/en-US/' . $profile;
                $result[$i][3] = $tds[1]->nodeValue;
                $result[$i][4] = $tds[2]->nodeValue;
                $result[$i][5] = $tds[3]->nodeValue;
                $result[$i][6] = $tds[4]->nodeValue;
                $result[$i][7] = $tds[5]->nodeValue;
                $i++;
            } else {
                if (stripos($tds[0]->nodeValue, 'More Results') !== false) {
                    break;
                }
            }
        }
    }
    catch (Error $e){
        echo $e->getMessage();
    }
    return $result;
}




/* Get User Input */
$options = getopt("",array("keywords::","company::","filename:"));

$keywords = key_exists('keywords',$options)?$options['keywords']:"";

$company = key_exists('company',$options)?$options['company']:"";

$file =key_exists('filename',$options)?$options['filename']:"";

if ($file==""){
    echo "\n Filename cannot be empty\n USAGE php getMemberList.php --keywords='test keywords' --company='test company' --filename=output.csv'\n";
    exit;
}

$f=fopen(rtrim($file, "\n\r"),'w');

/* Get Region List */
$regionList=getRegionIdList();

$results=array();
foreach ($regionList as $region) {
    echo "Getting Region:". $region->name;
    $html = getBNIData($region->id,urlencode(rtrim($company, "\n\r")),urlencode(rtrim($keywords, "\n\r")));
    $result = parseHTML($html);
    $results=array_merge($results,$result);
    echo " -- ".count($result)." members found \n";
}
usort($results, function($a, $b) {
    return [$a[0], $a[1], $a[3]]
        <=>
        [$b[0], $b[1], $b[3]];
});
$control=array("","","");
foreach ($results as $line){
    if ($control[0]==$line[0] and $control[1]==$line[1] and $control[3]==$line[3]){
        //duplicate
        echo "Duplicate ".$line[0]." ".$line[1]." ".$line[3];
    }
    else {
        fputcsv($f,$line);
        $control[0]=$line[0];$control[1]=$line[1]; $control[3]=$line[3];
    }

}
fclose($f);