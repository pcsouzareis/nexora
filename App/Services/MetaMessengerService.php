<?php
declare(strict_types=1);
namespace App\Services;
use App\Repositories\MetaChannelRepository;
use App\Support\Encryption;
use GuzzleHttp\Client;
final class MetaMessengerService {
 public function __construct(private readonly MetaChannelRepository $channels,private readonly Encryption $encryption) {}
 public function validSignature(array $channel,string $raw,?string $signature): bool {
  if(!$signature||empty($channel['sec003']))return false;
  try {$secret=$this->encryption->decrypt($channel['sec003']);} catch(\Throwable) {return false;}
  return hash_equals('sha256='.hash_hmac('sha256',$raw,$secret),$signature);
 }
 public function send(array $channel,string $recipient,string $message): bool {
  if(!$channel['outmet003']||empty($channel['pag003'])||empty($channel['met003'])||trim($message)==='')return false;
  try {
   $token=$this->encryption->decrypt($channel['met003']);
    if(($channel['tip003']??'')==='WhatsApp Cloud') {
     $response=(new Client(['timeout'=>20,'http_errors'=>false]))->post('https://graph.facebook.com/v22.0/'.rawurlencode((string)$channel['pag003']).'/messages',[
      'headers'=>['Authorization'=>'Bearer '.$token],
      'json'=>['messaging_product'=>'whatsapp','to'=>$recipient,'type'=>'text','text'=>['body'=>$message]],
     ]);
     return $response->getStatusCode()>=200&&$response->getStatusCode()<300;
    }
   $isInstagram=($channel['tip003']??'')==='Instagram';
   $url=$isInstagram
    ? 'https://graph.instagram.com/v22.0/'.$channel['pag003'].'/messages'
    : 'https://graph.facebook.com/v22.0/'.$channel['pag003'].'/messages';
   $payload=['recipient'=>['id'=>$recipient],'message'=>['text'=>$message]];
   if(!$isInstagram)$payload['messaging_type']='RESPONSE';
   $response=(new Client(['timeout'=>20,'http_errors'=>false]))->post($url,[
    'headers'=>['Authorization'=>'Bearer '.$token],
    'json'=>$payload,
   ]);
   return $response->getStatusCode()>=200&&$response->getStatusCode()<300;
  } catch(\Throwable) {return false;}
 }
}
