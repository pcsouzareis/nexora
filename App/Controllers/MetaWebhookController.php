<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Repositories\MetaChannelRepository;
use App\Services\MetaMessengerService;
use App\Services\WebhookMessageProcessor;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
final class MetaWebhookController {
 public function __construct(private readonly MetaChannelRepository $channels,private readonly MetaMessengerService $meta,private readonly WebhookMessageProcessor $processor) {}
 public function verify(ServerRequestInterface $request,ResponseInterface $response,array $args): ResponseInterface { $channel=$this->channel((string)($args['token']??''));$q=$request->getQueryParams();if($channel===null||(string)($q['hub_verify_token']??'')!==(string)($args['token']??''))return $response->withStatus(403);$response->getBody()->write((string)($q['hub_challenge']??''));return $response->withHeader('Content-Type','text/plain'); }
 public function receive(ServerRequestInterface $request,ResponseInterface $response,array $args): ResponseInterface { $channel=$this->channel((string)($args['token']??''));$raw=(string)$request->getBody();if($channel===null||!$this->meta->validSignature($channel,$raw,$request->getHeaderLine('X-Hub-Signature-256')))return $this->json($response,['error'=>'Não autorizado.'],403);$body=json_decode($raw,true);if(!is_array($body))return $this->json($response,['error'=>'JSON inválido.'],422);foreach((array)($body['entry']??[]) as $entry)foreach((array)($entry['messaging']??[]) as $event){$text=trim((string)($event['message']['text']??''));$sender=trim((string)($event['sender']['id']??''));$mid=trim((string)($event['message']['mid']??''));if($text===''||$sender===''||$mid===''||$sender===(string)$channel['pag003'])continue;try{$result=$this->processor->process((int)$channel['cod001'],(int)$channel['cod003'],['external_id'=>$sender,'name'=>null,'conversation_id'=>$sender,'message_id'=>$mid,'base_id'=>(int)$channel['cod005'],'message'=>$text]);}catch(InvalidArgumentException){continue;}$reply=$result['body']['reply']??null;if(is_array($reply)&&isset($reply['message']))$this->meta->send($channel,$sender,(string)$reply['message']);}return $this->json($response,['received'=>true]); }
 private function channel(string $token): ?array{return preg_match('/^[a-f0-9]{40}$/',$token)===1?$this->channels->findByWebhookToken($token):null;}
 private function json(ResponseInterface $response,array $data,int $status=200): ResponseInterface{$response->getBody()->write((string)json_encode($data,JSON_UNESCAPED_UNICODE));return $response->withHeader('Content-Type','application/json; charset=utf-8')->withStatus($status);}
}
