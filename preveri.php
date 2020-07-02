<?php
header('Content-type: text/plain; charset=utf-8');


$obvescanje= ObdelajRegije();

foreach ($obvescanje as $item) {
    PosljiSMS(ObdelajSporocilo($item), $item[1]);
}

var_dump($obvescanje);


//echo $array["channel"]["item"][0]["description"] ."\n";

//$test="<p>Obveščamo odjemalce električne energije, da bo zaradi rednih vzdrževalnih del na elektroenergetskih napravah prekinjena dobava električne energije na območju transformatorskih postaj: <strong>Sp. Muta - nizkonapetostni izvod Mrakič</strong>&nbsp;</p><ul class='dates-list'><li><strong>v sredo, 01. julija 2020 predvidoma med 10:00 in 14:00 uro</strong></li></ul><p>Zaradi organizacijskih ali vremenskih razlogov si pridržujemo pravico do preklica/odpovedi del.</p></br><p>&nbsp;</p>";
//$rezultat=PosljiRequestNaWit($array["channel"]["item"][2]["description"]);//$array["channel"]["item"][0]["description"]);
//var_dump($rezultat);
//var_dump(VrniPodatke($rezultat));
//$rezultat=$rezultat["entities"];
/*


$ura= $rezultat["Ura:Ura"][0]["body"];
echo "Ura:" . $ura;

$datum= $rezultat["Datum:Datum"][0]["body"];
echo "Datum:" . $datum;

$kraji= array();
echo "Kraji: ";
foreach ($rezultat["kraj:kraj"] as $item) {
    $kraji[] = $item["body"];
    echo $item["body"] . ", ";
}

$imeOmrezja= $rezultat["ImeOmrezja:ImeOmrezja"][0]["body"];
echo "Ime Omrežja:" . $imeOmrezja;

*/

function ObdelajSporocilo($polje)
{
    $sporocilo="Spoštovani! Elektro Celje je na spletni strani objavil, da bo za vaš naročen kraj: {0} na datum {1} {2} prišlo do izpada električne energije.\nLep pozdrav";

    $sporocilo=str_replace("{0}",$polje[0], $sporocilo);
    $sporocilo=str_replace("{1}",$polje[2], $sporocilo);
    $sporocilo=str_replace("{2}",$polje[3], $sporocilo);
    
    return $sporocilo;
}

function PosljiSMS($sms, $tel)
{
    $url = 'https://dev.nikigre.si/sms/api.php';
    $data = array('func' => '10000', 'user' => 'admin', 'message' => $sms, 'phone' => $tel);


    $options = array(
        'http' => array(
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data)
        )
    );
    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    if ($result === FALSE) { echo "Error"; } else { echo "OK"; }
}


function ObdelajRegije()
{
    $kraji= PridobiKrajeInStevilke();

    $obvescanje=array();

    foreach (array("velenje", "celje", "slovenj-gradec", "krsko") as $regija) {

        $xmlSpodatki= ObdelajKraj($regija);

        foreach ($xmlSpodatki as $item) {
            foreach ($item["Kraji"] as $IzpadKraja) {
                if(in_array($IzpadKraja, $kraji[0]))
                {
                    //echo "Je v polju:" . $IzpadKraja;
                    foreach ($kraji[1] as $enoObvestilo) {

                        //echo $enoObvestilo[0] . "==" . $IzpadKraja;
                        if($enoObvestilo[0]==$IzpadKraja)
                        {
                            //echo "sem v if";
                            $vmesni = array($IzpadKraja, $enoObvestilo[1]);
                            if(array_key_exists("Datum",$item))
                            {
                                $vmesni[] = $item["Datum"];
                            }
                            else
                            {
                                $vmesni[]= "NULL";
                            }

                            if(array_key_exists("Ura",$item))
                            {
                                $vmesni[] = $item["Ura"];
                            }
                            else
                            {
                                $vmesni[]= "NULL";
                            }

                            $obvescanje[]= $vmesni;
                        }
                    }
                }
            }
        }
    }

    return $obvescanje;
}

function ObdelajKraj($kraj)
{
    $ob = simplexml_load_file("https://www.elektro-celje.si/si/rss.ashx?k=". $kraj);
    
    //$ob = simplexml_load_file("xml.xml");
    
    $json = json_encode($ob);
    $array = json_decode($json, true);
    
    $array1 = array();

    foreach ($array["channel"]["item"] as $item) {
        //echo $item["description"] . "<br>";
        $rezultat = PosljiRequestNaWit($item["description"]);
    
        $podatki=VrniPodatke($rezultat);

        $array1[] = $podatki;
        //var_dump($podatki);
    }
    return $array1;
}

function PosljiRequestNaWit($sporocilo)
{
    $sporocilo = str_replace("<ul class='dates-list'><li><strong>"," ",$sporocilo);
    $sporocilo = strip_tags($sporocilo);
    $sporocilo = str_replace("Obveščamo odjemalce električne energije, da bo zaradi rednih vzdrževalnih del na elektroenergetskih napravah prekinjena dobava električne energije na območju","",$sporocilo);
    $sporocilo = str_replace("&nbsp;","",$sporocilo);
    $sporocilo = str_replace("Zaradi organizacijskih ali vremenskih razlogov si pridržujemo pravico do preklica/odpovedi del.","",$sporocilo);
    $sporocilo = str_replace("\n"," ",$sporocilo);
   
    $c = curl_init("https://api.wit.ai/message?v=20200701&q=" . urlencode(substr($sporocilo,0,280)));

    $headr = array();
    $headr[] = 'Content-length: 0';
    $headr[] = 'Content-type: application/json';
    $headr[] = 'Authorization: Bearer 2GL7243PI6D73EX2OEUG7DNQIURYCRK2';

    curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($c, CURLOPT_HTTPHEADER,$headr);
    $html = curl_exec($c);

    if (curl_error($c))
        die(curl_error($c));

    curl_close($c);

    $html = str_replace('"traits":{}};',"",$html);

    return json_decode($html, true);
}

function VrniPodatke($podatki)
{
    $podatki=$podatki["entities"];
    $polje= array();

    if(array_key_exists("Ura:Ura", $podatki))
    {
        $ura= $podatki["Ura:Ura"][0]["body"];
        //echo "Ura:" . $ura;
        $polje += array("Ura" => $ura);
    }
    else{
        $polje += array("Ura" => "NULL");
    }

    if(array_key_exists("Ura:Ura", $podatki))
    {
        $datum= $podatki["Datum:Datum"][0]["body"];
        //echo "Datum:" . $datum;
        $polje += array("Datum" => $datum);
    }
    else{
        $polje += array("Datum" => "NULL");
    }

    if(array_key_exists("kraj:kraj", $podatki))
    {
        $kraji= array();
        echo "Kraji: ";
        foreach ($podatki["kraj:kraj"] as $item) {
            $kraji[] = strtolower($item["body"]);
            //echo $item["body"] . ", ";
        }
        $polje += array("Kraji" => $kraji);
    }
    else{
        $polje += array("Kraji" => "NULL");
    }
  
    if(array_key_exists("ImeOmrezja:ImeOmrezja", $podatki))
    {
        $imeOmrezja= $podatki["ImeOmrezja:ImeOmrezja"][0]["body"];
        //echo "Ime Omrežja:" . $imeOmrezja;
        $polje += array("ImeOmrezja" => $imeOmrezja);
    }
    else{
        $polje += array("ImeOmrezja" => "NULL");
    }

    return $polje;
}

function PridobiKrajeInStevilke()
{
    include("baza.php");

    $sql="SELECT Kraj, TelSt FROM narocilo JOIN uporabniki ON ID_uporabnik=ID_oseba WHERE 1";

    $result = $conn->query($sql);

    $polje=array();

    $kraji=array();
    while($row = $result->fetch_assoc()) {
        $polje[]= array(strtolower($row['Kraj']), $row["TelSt"]);
        if(!in_array($row['Kraj'],$kraji))
        {
            $kraji[] = strtolower($row['Kraj']);
        }
    }
    $conn->close();

    $polje1=array();
    $polje1[] = $kraji;
    $polje1[]= $polje;   
    return $polje1;
}

?>