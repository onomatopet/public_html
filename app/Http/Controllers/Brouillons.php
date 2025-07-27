// Validation

        $this->validate($request, [
            'distributeur_id' => 'required|min:7|max:7',
            'value_pv' => 'required|min:1',
            'id_produit' => 'required|min:1',
            'Qt' => 'required|min:1',
            'value' => 'required|min:2',
            'prix_product' => 'required|min:2|max:120',
            'numbers' => 'required|min:1',
            'created_at' => 'required|date_format:d/m/Y'
        ]);
        //return Carbon::createFromFormat('d-m-Y', '30-12-2023');

        $pv = Level::where('distributeur_id', '=', $request->distributeur_id)->first();
        $etoiles_requis = Etoile::where('etoile_level', ($pv->etoiles+1))->first();
        //return $etoiles_requis->cumul_individuel;

        $cumul_individuel = $pv->cumul_individuel+$request->value_pv;
        //$cumul_collectif = $pv->cumul_collectif+$request->value_pv;
        $request_created = Carbon::parse($pv->created_at)->month;
        $RequestedDate =  Carbon::createFromFormat('d/m/Y', $request->created_at)->format('d-m-Y');
        $db_created_date = Carbon::parse($RequestedDate)->month;
        $diff = ($request_created == $db_created_date) ? 1 : 0;
        return $diff;
        $new_cumul = ($diff == 1) ? ($pv->new_cumul + $request->value_pv) : $request->value_pv;
        $cumul_total = ($diff == 1) ? ($pv->cumul_total+$request->value_pv) : $request->value_pv;
        /*
        $cumul_individuel = $pv->cumul_individuel + $request->value_pv;
        $cumul_collectif = $pv->cumul_collectif + $request->value_pv;
        */
        $reseauCheckReseau = Distributeur::where('id_distrib_parent', $request->distributeur_id)->get();
        $verif = $reseauCheckReseau->isEmpty();
        //return $pv->etoiles;
        if($verif)
        {
            switch($pv->etoiles)
            {
                case 1:
                    //return 'Passage du niveau 1* au niveau 2*';
                    $etoiles = ($cumul_individuel >= $etoiles_requis->cumul_individuel) ? 2 : 1;
                    //return $etoiles;
                break;
                case 2:
                    // Passage du niveau 2* au niveau 3*
                    $etoiles = ($cumul_individuel >= $etoiles_requis->cumul_individuel) ? 3 : 2;
                break;
                case 3:
                    // Passage du niveau 3* au niveau 4*
                    if($cumul_individuel >= $etoiles_requis->cumul_individuel)
                    {
                        $etoiles = 4;
                    }
                    else {
                        $etoiles = 3;
                    }
                break;
                case 4:
                    // Passage du niveau 3* au niveau 4*
                    $etoiles = ($cumul_individuel >= $etoiles_requis->cumul_individuel) ? 5 : 4;
                break;
                default: $etoiles = $pv->etoiles;
            }

        } else {
            /*
            $reseauCheckLevel = Level::where('etoiles', '>=', 3)->where('id_distrib_parent', $request->distributeur_id)->get();
            $checkTotalPvReseau = Level::select('id_distrib_parent', Level::raw('SUM(cumul_collectif) as total_pv'))
                ->where('id_distrib_parent', $request->distributeur_id)
                ->get();
            */

            switch($pv->etoiles)
            {
                case 1:
                    //return 'Passage du niveau 1* au niveau 2* avec fileul';
                    $etoiles = ($cumul_individuel >= $etoiles_requis->cumul_individuel) ? 2 : 1;
                break;
                case 2:
                    // Passage du niveau 2* au niveau 3*
                    $etoiles = ($cumul_individuel >= $etoiles_requis->cumul_individuel) ? 3 : 2;
                break;
                case 3:
                    //return $etoiles_requis->cumul_collectif_2;
                    if($cumul_collectif >= $etoiles_requis->cumul_collectif_2){
                        $nbthird = $this->getSubdistribids($pv->distributeur_id, 3);
                        if(count($nbthird) >= 3)
                        {
                            $etoiles = 4;
                        }else {
                            $etoiles = 3;
                        }
                    }
                    elseif($cumul_collectif >= $etoiles_requis->cumul_collectif_1){
                        $nbthird = $this->getSubdistribids($pv->distributeur_id, 3);
                        if(count($nbthird) >= 2)
                        {
                            $etoiles = 4;
                        }else {
                            $etoiles = 3;
                        }
                    }
                    else {
                        $etoiles = 3;
                    }
                break;
                case 4:
                    //return $etoiles_requis->cumul_collectif_2;
                    if($cumul_collectif >= $etoiles_requis->cumul_collectif_1){
                        $nbthird = $this->getSubdistribids($pv->distributeur_id, 4);
                        if(count($nbthird) >= 2)
                        {
                            $etoiles = 5;
                        }
                        elseif($cumul_collectif >= $etoiles_requis->cumul_collectif_2){
                            $nbthird = $this->getSubdistribids($pv->distributeur_id, 4);

                            if(count($nbthird) >= 3)
                            {
                                $etoiles = 5;
                            }
                            elseif($cumul_collectif >= $etoiles_requis->cumul_collectif_3){
                                $nbthird = $this->getSubdistribids($pv->distributeur_id, 4);
                                $nbforth = $this->getSubdistribids($pv->distributeur_id, 3);
                                if((count($nbthird) >= 2) && (count($nbforth) >= 4))
                                {
                                    $etoiles = 5;
                                }else {
                                    $etoiles = 4;
                                }
                            }
                        }
                        else {
                            $etoiles = 4;
                        }
                    }
                    else {
                        $etoiles = 4;
                    }
                break;
                case 5:
                    // Passage du niveau 3* au niveau 4*
                    if($cumul_collectif >= $etoiles_requis->cumul_collectif_1){
                        $nbthird = $this->getSubdistribids($pv->distributeur_id, 5);
                        if(count($nbthird) >= 2)
                        {
                            $etoiles = 6;
                        }
                        elseif($cumul_collectif >= $etoiles_requis->cumul_collectif_2){
                            $nbthird = $this->getSubdistribids($pv->distributeur_id, 5);

                            if(count($nbthird) >= 3)
                            {
                                $etoiles = 6;
                            }
                            elseif($cumul_collectif >= $etoiles_requis->cumul_collectif_3){
                                $nbthird = $this->getSubdistribids($pv->distributeur_id, 5);
                                $nbforth = $this->getSubdistribids($pv->distributeur_id, 4);
                                if((count($nbthird) >= 2) && (count($nbforth) >= 4))
                                {
                                    $etoiles = 6;
                                }else {
                                    $etoiles = 5;
                                }
                            }
                            elseif($cumul_collectif >= $etoiles_requis->cumul_collectif_4){
                                $nbthird = $this->getSubdistribids($pv->distributeur_id, 5);
                                $nbforth = $this->getSubdistribids($pv->distributeur_id, 4);
                                if((count($nbthird) >= 2) && (count($nbforth) >= 6))
                                {
                                    $etoiles = 6;
                                }else {
                                    $etoiles = 5;
                                }
                            }
                        }
                        else {
                            $etoiles = 5;
                        }
                    }
                    else {
                        $etoiles = 5;
                    }
                break;
                case 6:
                    // Passage du niveau 5* au niveau 6*
                    if($cumul_collectif >= $etoiles_requis->cumul_collectif_1){
                        $nbthird = $this->getSubdistribids($pv->distributeur_id, 6);
                        if(count($nbthird) >= 2)
                        {
                            $etoiles = 7;
                        }
                        elseif($cumul_collectif >= $etoiles_requis->cumul_collectif_2){
                            $nbthird = $this->getSubdistribids($pv->distributeur_id, 6);

                            if(count($nbthird) >= 3)
                            {
                                $etoiles = 7;
                            }
                            elseif($cumul_collectif >= $etoiles_requis->cumul_collectif_3){
                                $nbthird = $this->getSubdistribids($pv->distributeur_id, 6);
                                $nbforth = $this->getSubdistribids($pv->distributeur_id, 5);
                                if((count($nbthird) >= 2) && (count($nbforth) >= 4))
                                {
                                    $etoiles = 7;
                                }else {
                                    $etoiles = 6;
                                }
                            }
                            elseif($cumul_collectif >= $etoiles_requis->cumul_collectif_4){
                                $nbthird = $this->getSubdistribids($pv->distributeur_id, 6);
                                $nbforth = $this->getSubdistribids($pv->distributeur_id, 5);
                                if((count($nbthird) >= 1) && (count($nbforth) >= 6))
                                {
                                    $etoiles = 7;
                                }else {
                                    $etoiles = 6;
                                }
                            }
                        }
                        else {
                            $etoiles = 6;
                        }
                    }
                    else {
                        $etoiles = 6;
                    }
                break;
                case 7:
                    // Passage du niveau 7* au niveau 8*
                    if($cumul_collectif >= $etoiles_requis->cumul_collectif_1){
                        $nbthird = $this->getSubdistribids($pv->distributeur_id, 7);
                        if(count($nbthird) >= 2)
                        {
                            $etoiles = 8;
                        }
                        elseif($cumul_collectif >= $etoiles_requis->cumul_collectif_2){
                            $nbthird = $this->getSubdistribids($pv->distributeur_id, 7);

                            if(count($nbthird) >= 3)
                            {
                                $etoiles = 8;
                            }
                            elseif($cumul_collectif >= $etoiles_requis->cumul_collectif_3){
                                $nbthird = $this->getSubdistribids($pv->distributeur_id, 7);
                                $nbforth = $this->getSubdistribids($pv->distributeur_id, 6);
                                if((count($nbthird) >= 2) && (count($nbforth) >= 4))
                                {
                                    $etoiles = 8;
                                }else {
                                    $etoiles = 7;
                                }
                            }
                            elseif($cumul_collectif >= $etoiles_requis->cumul_collectif_4){
                                $nbthird = $this->getSubdistribids($pv->distributeur_id, 7);
                                $nbforth = $this->getSubdistribids($pv->distributeur_id, 6);
                                if((count($nbthird) >= 1) && (count($nbforth) >= 6))
                                {
                                    $etoiles = 8;
                                }else {
                                    $etoiles = 7;
                                }
                            }
                        }
                        else {
                            $etoiles = 7;
                        }
                    }
                    else {
                        $etoiles = 7;
                    }
                break;
                case 8:
                    // Passage du niveau 8* au niveau 9*
                    if($cumul_collectif >= $etoiles_requis->cumul_collectif_1){
                        $nbthird = $this->getSubdistribids($pv->distributeur_id, 8);
                        if(count($nbthird) >= 2)
                        {
                            $etoiles = 9;
                        }
                        elseif($cumul_collectif >= $etoiles_requis->cumul_collectif_2){
                            $nbthird = $this->getSubdistribids($pv->distributeur_id, 8);

                            if(count($nbthird) >= 3)
                            {
                                $etoiles = 9;
                            }
                            elseif($cumul_collectif >= $etoiles_requis->cumul_collectif_3){
                                $nbthird = $this->getSubdistribids($pv->distributeur_id, 8);
                                $nbforth = $this->getSubdistribids($pv->distributeur_id, 7);
                                if((count($nbthird) >= 2) && (count($nbforth) >= 4))
                                {
                                    $etoiles = 9;
                                }else {
                                    $etoiles = 8;
                                }
                            }
                            elseif($cumul_collectif >= $etoiles_requis->cumul_collectif_4){
                                $nbthird = $this->getSubdistribids($pv->distributeur_id, 8);
                                $nbforth = $this->getSubdistribids($pv->distributeur_id, 7);
                                if((count($nbthird) >= 1) && (count($nbforth) >= 6))
                                {
                                    $etoiles = 9;
                                }else {
                                    $etoiles = 8;
                                }
                            }
                        }
                        else {
                            $etoiles = 8;
                        }
                    }
                    else {
                        $etoiles = 8;
                    }
                break;
                case 9:
                    // Passage du niveau 9* au niveau 10*
                    $nbthird = $this->getSubdistribids($pv->distributeur_id, 9);
                    if(count($nbthird) >= 2)
                    {
                        $etoiles = 10;
                    }
                    else {
                        $etoiles = 9;
                    }
                break;
                case 10:
                    // Passage du niveau 10* au niveau 11*
                    //return 'Passage du niveau 10* au niveau 11*';
                    $nbthird = $this->getSubdistribids($pv->distributeur_id, 9);
                    if(count($nbthird) >= 3)
                    {
                        $etoiles = 11;
                    }
                    else {
                        $etoiles = 10;
                    }
                break;
                default: $etoiles = $pv->etoiles;
            }

            //$etoiles = $this->reseauCheckLevel($request->distributeur_id, $cumul_collectif, $pv->etoiles, true);
            //return $etoiles;
        }
        /*
        $array_final = array(
            "distributeur_id" => $request->distributeur_id,
            "new_cumul" => $new_cumul,
            "cumul_total" => $cumul_total,
            "cumul_individuel" => $cumul_individuel,
            "cumul_collectif" => $cumul_collectif,
            "idproduit" => $request->idproduit,
            "Quantité" => $request->Qt,
            "prix_product" => $request->prix_product,
            "Total" => $request->value,
        );
        return $array_final;
        */
        $levels = Level::where('distributeur_id', '=', $request->distributeur_id)->firstOrFail();
        $levels->etoiles = $etoiles;
        $levels->new_cumul = $new_cumul;
        $levels->cumul_total= $cumul_total;
        $levels->cumul_individuel = $cumul_individuel;
        $levels->cumul_collectif= $cumul_collectif;

        $RequestDate =  Carbon::createFromFormat('d/m/Y', $request->created_at)->format('d-m-Y');
        if(isset($request->created_at)) $levels->created_at = Carbon::parse($RequestDate)->month;
        $levels->update();

        $levelHistory = new Level_History();
        $levelHistory->rang = $pv->rang;
        $levelHistory->distributeur_id = $request->distributeur_id;
        $levelHistory->etoiles = $etoiles;
        $levelHistory->cumul_individuel = $cumul_individuel;
        $levelHistory->new_cumul = $new_cumul;
        $levelHistory->cumul_total= $cumul_total;
        $levelHistory->cumul_collectif= $cumul_collectif;
        $levelHistory->id_distrib_parent = $pv->id_distrib_parent;

        if(isset($request->created_at)) $levelHistory->created_at = Carbon::parse($RequestDate);
        $levelHistory->save();

        $products = new Achat();
        $products->id_distrib_parent = $pv->id_distrib_parent;
        $products->distributeur_id = $request->distributeur_id;
        $products->pointvaleur = $request->value_pv;
        $products->products_id = $request->idproduit;
        $products->qt = $request->Qt;
        $products->montant = $request->value;

        if(isset($request->created_at)) $products->created_at = Carbon::parse($RequestDate);
        $products->save();

        flash(message: 'action executer avec succes')->success();
        return back();
