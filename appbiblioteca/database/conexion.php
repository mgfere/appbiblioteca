    <?php

// $host = '192.168.10.20'; 
    //$host = 'localhost'; 
    //$dbName = 'biblioteca';
    //$user = 'luis';
    //$password = '1234';

    

    $host = '172.16.0.155';
    $dbName = 'bibliotecaDB';
    $user = 'uttn_alumno1';
    $password = 'radiofax6548';

    try {
        $dbh = new PDO("mysql:host=$host;dbname=$dbName", $user, $password);
        // $dbh = new PDO("mysql:host=$host;dbname=$dbName", $user, $password);

        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }




    ?>
