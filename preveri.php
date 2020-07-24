<?php
header('Content-type: text/plain; charset=utf-8');

$obvescanje= ObdelajRegije();

var_dump($obvescanje);

foreach ($obvescanje as $item) {
   PosljiSMS($item);
}

function PosljiSMS($item)
{
    $url = 'https://dev.nikigre.si/sms/api.php';
    $data = array('func' => '10000', 'user' => 'admin', 'message' => $item[0], 'phone' => $item[1]);


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

        foreach ($xmlSpodatki as $item) { //Za vsak skupek podatkov -> $item = en skupek
            foreach ($item["Kraji"] as $IzpadKraja) { //Za vsak kraj v skupku podatkov -> $IzpadKraja = en kraj
                
                foreach ($kraji as $prijavaNaIzpad) { // Za vsako prijavo na izpad -> $prijavaNaIzpad = kraj in tel št
                    if(strtolower($prijavaNaIzpad[0]) ==strtolower($IzpadKraja))
                    {
                        $sporocilo="Spoštovani! Elektro Celje je na spletni strani objavil, da bo za vaš naročen kraj: {0} na datum ";

                        $sporocilo=str_replace("{0}",$prijavaNaIzpad[0], $sporocilo);

                        for ($i=0; $i < count($item['Datum']); $i++) {

                            $sporocilo .= $item['Datum'][$i];

                            if(count($item['Ura'])>= $i)
                            {
                                $sporocilo .= " " . $item['Ura'][$i];
                            }

                            if($i+1< count($item['Datum']))
                             $sporocilo .= " in ";
                        }

                        $sporocilo .=" prišlo do izpada električne energije.\nLep pozdrav";

                        $obvescanje[] = array($sporocilo, $prijavaNaIzpad[1]);
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

    //var_dump($array1);
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
        $ura= array();

        foreach ($podatki["Ura:Ura"] as $item) {
            $ura[] = strtolower($item["body"]);
        }

        $polje += array("Ura" => $ura);
    }
    else{
        $polje += array("Ura" => "NULL");
    }

    if(array_key_exists("Datum:Datum", $podatki))
    {
        $datum= array();
    
        foreach ($podatki["Datum:Datum"] as $item) {
            $datum[] = strtolower($item["body"]);
        }

        $polje += array("Datum" => $datum);
    }
    else{
        $polje += array("Datum" => "NULL");
    }

    if(array_key_exists("kraj:kraj", $podatki))
    {
        $kraji= array();

        foreach ($podatki["kraj:kraj"] as $item) {
            $kraji[] = strtolower($item["body"]);
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

    while($row = $result->fetch_assoc()) {

        $polje[]= array(strtolower($row['Kraj']), $row["TelSt"]);
    }
    $conn->close();
 
    return $polje;
}
?>