<?php
// Minimalistický modul pro přepočet dnešního těsta bez faktorů

if (!function_exists('getPizzaOrdersDb')) {
    // Pokud už ve vašem projektu existuje getPizzaOrdersDb(), tento blok se nespustí
    function getPizzaOrdersDb(){
        static $pdo=null; if($pdo) return $pdo;
        $pdo=new PDO('mysql:host=127.0.0.1;dbname=pizza_orders;charset=utf8mb4','pizza_user','pizza',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        return $pdo;
    }
}

function recalcDoughForDate($date){
    try {
        $pdo=getPizzaOrdersDb();
        // Zajisti daily_supplies řádek
        $st=$pdo->prepare('SELECT id,pizza_total,pizza_used FROM daily_supplies WHERE date=?');
        $st->execute([$date]);
        $ds=$st->fetch(PDO::FETCH_ASSOC);
        if(!$ds){
            $pdo->prepare("INSERT INTO daily_supplies (date,pizza_total,burrata_total,pizza_used,burrata_used,updated_by,updated_at,pizza_reserved,pizza_walkin,burrata_reserved,burrata_walkin) VALUES (?,120,15,0,0,'AUTO',NOW(),0,0,0,0)")->execute([$date]);
            $st->execute([$date]);
            $ds=$st->fetch(PDO::FETCH_ASSOC);
        }
        if(!$ds) return ['ok'=>false,'error'=>'Nelze vytvořit daily_supplies'];

        // Součet party_size pro confirmed + seated
        $rs=$pdo->prepare("SELECT COALESCE(SUM(party_size),0) AS reserved FROM reservations WHERE reservation_date=? AND status IN ('confirmed','seated')");
        $rs->execute([$date]);
        $row=$rs->fetch(PDO::FETCH_ASSOC);
        $pizzaReserved=(int)$row['reserved'];

        $pizzaTotal=(int)$ds['pizza_total'];
        $pizzaUsed=(int)$ds['pizza_used'];
        if($pizzaReserved>$pizzaTotal) $pizzaReserved=$pizzaTotal; // ochrana
        $pizzaWalkin = max(0,$pizzaTotal - $pizzaReserved - $pizzaUsed);

        $upd=$pdo->prepare("UPDATE daily_supplies SET pizza_reserved=?, pizza_walkin=?, updated_by='AUTO', updated_at=NOW() WHERE id=?");
        $upd->execute([$pizzaReserved,$pizzaWalkin,$ds['id']]);
        return ['ok'=>true,'date'=>$date,'pizza_total'=>$pizzaTotal,'pizza_used'=>$pizzaUsed,'pizza_reserved'=>$pizzaReserved,'pizza_walkin'=>$pizzaWalkin];
    } catch(Throwable $e){ error_log('[DOUGH RECALC MINIMAL] '.$e->getMessage()); return ['ok'=>false,'error'=>$e->getMessage()]; }
}

function incrementPizzaUsed($date,$qty,$by='ORDER'){
    if($qty<=0) return true; // nic k navýšení
    try{
        $pdo=getPizzaOrdersDb();
        $pdo->beginTransaction();
        $sel=$pdo->prepare('SELECT id,pizza_total,pizza_reserved,pizza_used FROM daily_supplies WHERE date=? FOR UPDATE');
        $sel->execute([$date]);
        $row=$sel->fetch(PDO::FETCH_ASSOC);
        if(!$row){
            $pdo->prepare("INSERT INTO daily_supplies (date,pizza_total,burrata_total,pizza_used,burrata_used,updated_by,updated_at,pizza_reserved,pizza_walkin,burrata_reserved,burrata_walkin) VALUES (?,120,15,0,0,?,NOW(),0,0,0,0)")->execute([$date,$by]);
            $sel->execute([$date]);
            $row=$sel->fetch(PDO::FETCH_ASSOC);
        }
        $pizzaUsedNew=(int)$row['pizza_used'] + $qty;
        $pizzaTotal=(int)$row['pizza_total'];
        $pizzaReserved=(int)$row['pizza_reserved'];
        $pizzaWalkin=max(0,$pizzaTotal - $pizzaReserved - $pizzaUsedNew);
        $upd=$pdo->prepare('UPDATE daily_supplies SET pizza_used=?, pizza_walkin=?, updated_by=?, updated_at=NOW() WHERE id=?');
        $upd->execute([$pizzaUsedNew,$pizzaWalkin,$by,$row['id']]);
        $pdo->commit();
        return true;
    }catch(Throwable $e){ if(isset($pdo) && $pdo->inTransaction()) $pdo->rollBack(); error_log('[INCREMENT PIZZA USED] '.$e->getMessage()); return false; }
}

function recalcTodayIf($date){ if($date===date('Y-m-d')) return recalcDoughForDate($date); return null; }