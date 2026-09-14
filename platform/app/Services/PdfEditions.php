<?php
namespace App\Services;
use App\Models\{Product,SourceRecord};
use App\Jobs\GeneratePdfEdition;
use Illuminate\Support\Facades\DB;

class PdfEditions
{
    public function queue(Product|SourceRecord $subject,int $user): void
    {
        DB::transaction(function()use($subject,$user){
            $subject=$subject->newQuery()->lockForUpdate()->findOrFail($subject->id);
            abort_if(in_array($subject->metadata['pdf_job']['status']??'',['queued','processing'],true),409);
            $subject->updateQuietly(['metadata'=>[...($subject->metadata??[]),'pdf_job'=>['status'=>'queued','user_id'=>$user]]]);
            GeneratePdfEdition::dispatch($subject instanceof Product?'product':'record',$subject->id,$user,$this->version($subject))->afterCommit();
        });
    }
    public function version(Product|SourceRecord $subject): string
    {
        return hash('sha256',json_encode([$subject->title,$subject instanceof Product?$subject->description:$subject->body,$subject->author??($subject->metadata['author']??''),$subject->contents??'',$subject instanceof Product?($subject->metadata['edition_text']??''):''],JSON_THROW_ON_ERROR));
    }
    public function render(Product|SourceRecord $subject): string
    {
        $body=$subject instanceof Product?($subject->metadata['edition_text']??$subject->description):$subject->body;
        abort_if(mb_strlen($body??'')>200000,422);
        $options=new \Dompdf\Options(['isRemoteEnabled'=>false,'isPhpEnabled'=>false,'isJavascriptEnabled'=>false,'defaultFont'=>'DejaVu Sans','chroot'=>storage_path('app')]);
        $pdf=new \Dompdf\Dompdf($options);
        $pdf->loadHtml('<!doctype html><html><meta charset="utf-8"><style>body{font:12px DejaVu Sans;line-height:1.6}h1{font-size:24px}footer{color:#666}</style><h1>'.e($subject->title).'</h1><p>'.e($subject->author??($subject->metadata['author']??'')).'</p><div>'.nl2br(e(strip_tags($body??''))).'</div><footer>'.e(app(Settings::class)->get('site_name',config('platform.brand'))).'</footer></html>','UTF-8');
        $pdf->setPaper('A4');$pdf->render();return $pdf->output();
    }
}
