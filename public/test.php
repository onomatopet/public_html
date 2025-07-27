<?php

    $serveur = 'localhost';
	$user='root';
	$passwd = '';
	$bdd = 'db_eternal_apps';
	
	try	{		
		
		$cnx = new PDO('mysql:host='.$serveur.';dbname='.$bdd, $user, $passwd, array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));
		$cnx->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$sql = "SELECT * FROM `distributeurs`";
		$qid = $cnx->prepare($sql);		
		$qid->execute();
		$i = 0;
		while($row = $qid->fetch(PDO::FETCH_OBJ)) {
				
			$array[$i] = array(					
				'id' => $row->id,
                "distributeur_id" => $row->distributeur_id,
                "nom_distributeur" => $row->nom_distributeur,
                "pnom_distributeur" => $row->pnom_distributeur,
                "etoiles_id" => $row->etoiles_id,
                "tel_distributeur" => $row->tel_distributeur,
                "adress_distributeur" => $row->adress_distributeur,
                "id_distrib_parent" => $row->id_distrib_parent,
                "created_at" => $row->created_at
			);

			$i++;
		}
	}	
	
	// Récupère l'exception et l'affiche avant de mettre fin à l'exécution du script.
	catch (PDOException $e)	{
		
		echo 'N° : '.$e->getCode().'<br />';
		die ('Erreur : '.$e->getMessage().'<br />');
	}
	
	echo json_encode(array('Distributeurs'=>$array));
	
?>