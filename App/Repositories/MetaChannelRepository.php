<?php
declare(strict_types=1);
namespace App\Repositories;
use App\Support\Database;
use PDO;
final class MetaChannelRepository {
 public function __construct(private readonly Database $database) {}
 public function findByWebhookToken(string $token): ?array { $s=$this->database->pdo()->prepare("SELECT cod003,cod001,cod005,tip003,pag003,met003,sec003,outmet003 FROM n003 WHERE pub003=:token AND tip003 IN ('Facebook','Instagram') AND sts003=TRUE LIMIT 1"); $s->execute(['token'=>$token]); $r=$s->fetch(PDO::FETCH_ASSOC); return $r===false?null:$r; }
 public function save(int $company,int $channel,string $page,?string $access,?string $secret,bool $enabled): void { $s=$this->database->pdo()->prepare("UPDATE n003 SET pag003=:page,met003=COALESCE(:access,met003),sec003=COALESCE(:secret,sec003),outmet003=:enabled,atu003=CURRENT_TIMESTAMP WHERE cod001=:company AND cod003=:channel AND tip003 IN ('Facebook','Instagram')");$s->execute(['company'=>$company,'channel'=>$channel,'page'=>$page,'access'=>$access,'secret'=>$secret,'enabled'=>$enabled?'true':'false']); }
}
