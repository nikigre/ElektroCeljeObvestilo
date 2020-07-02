<?php
if(isset($_POST['ime']))
{
    include "baza.php";

    $sql="INSERT INTO uporabniki( TelSt, Ime) VALUES ('" . mysqli_real_escape_string($conn, $_POST['tel']) . "','" . mysqli_real_escape_string($conn, $_POST['ime']) . "')";

    if ($conn->query($sql) === TRUE) {
    } else {
        if(!(strpos($conn->error, "Duplicate entry") !==false))
            echo "Error: " . $sql . "<br>" . $conn->error;
    }

    $conn->close();

    $id= PridobiIDosebe($_POST['tel']);

    VstaviKraj($id, $_POST['kraj']);
    
    echo "<h1>Naročilo je bilo oddano!</h1>";
    //header( "refresh:2;url=https://nikigre.si/izpadi-elektrike-elektra-celje/" );
    echo "<script>
       var start = new Date().getTime();
    var end = start;
   while(end < start + 2000) {
     end = new Date().getTime();
  }
    window.history.back();</script>";
}

function PridobiIDosebe($telst)
{
    
    include "baza.php";
    $sql = "SELECT ID_uporabnik FROM uporabniki WHERE TelSt='" . mysqli_real_escape_string($conn, $telst) . "'";
    $result = $conn->query($sql);

    $id="";
    if ($result->num_rows > 0) {
    // output data of each row
    while($row = $result->fetch_assoc()) {
       $id= $row["ID_uporabnik"];
    }
    }
    $conn->close();

    return $id;
}

function VstaviKraj($ID, $kraj)
{

    include "baza.php";
    $sql = "INSERT INTO `narocilo`(Kraj, ID_oseba)
SELECT
    '" . mysqli_real_escape_string($conn, $kraj) . "',
    " . $ID . "
FROM DUAL
WHERE NOT EXISTS
    (
    SELECT
        *
    FROM
        narocilo
    WHERE
        Kraj = '" . mysqli_real_escape_string($conn, $kraj) . "' AND ID_oseba = " . $ID . "
    LIMIT 1
)"; 
    if ($conn->query($sql) === TRUE) {
    } else {
      echo "Error: " . $sql . "<br>" . $conn->error;
    }
    
    $conn->close();

}
?>