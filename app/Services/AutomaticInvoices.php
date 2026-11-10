<?php
namespace App\Services;

use Carbon\CarbonImmutable as Time;
use Illuminate\Support\Facades\{DB,Validator};

final class AutomaticInvoices
{
    public function run(int $limit=10): array
    {
        $counts=['issued'=>0,'waiting'=>0];
        foreach(DB::table('salon_state')->pluck('id') as $id){
            if(array_sum($counts)>=$limit)break;
            app(Tenant::class)->run((int)$id,function()use(&$counts,$limit){
                $s=app(SalonStore::class)->read();
                if(empty($s['settings']['autoInvoice'])||!PaidFeatures::enabled($s))return;
                $bookings=array_column($s['bookings'],null,'id');
                $clients=array_column($s['clients'],null,'id');
                $invoiced=[];$totals=[];$latest=[];
                foreach($s['invoiceRequests'] as $invoice){
                    if(in_array($invoice['status'],['issuing','issued','unknown'],true))$invoiced[$invoice['bookingId']??'']=true;
                }
                foreach($s['payments'] as $payment){
                    if($payment['status']!=='paid')continue;
                    $key=$payment['bookingId'];
                    $totals[$key]=($totals[$key]??0)+$payment['amount']-($payment['refundedAmount']??0);
                    $latest[$key]=$payment;
                }
                foreach(array_reverse($latest,true) as $key=>$payment){
                    if(array_sum($counts)>=$limit)break;
                    $booking=$bookings[$key]??null;
                    if(!$booking||isset($invoiced[$key])||abs($totals[$key]-Offers::total($booking))>0.001)continue;
                    if(!empty($s['autoInvoiceAttempts'][$key])&&Time::parse($s['autoInvoiceAttempts'][$key])->gt(now()->subHour()))continue;
                    $client=$clients[$booking['clientId']??'']??[];
                    $guest=$booking['guestId']??$client['guestId']??null;
                    $buyer=$payment['buyer']??($guest?GuestBilling::get((int)$guest):[]);
                    $buyer+=['country'=>'HU','email'=>$booking['email']??''];
                    $reason='';
                    foreach(['name','zip','city','address'] as $field){
                        if(empty($buyer[$field]))$reason='Hiányos vendég-számlázási adatok.';
                    }
                    if(!$reason){
                        try{
                            Validator::make(['buyer'=>$buyer],[
                                'buyer.name'=>'required|string|max:200','buyer.email'=>'nullable|email',
                                'buyer.country'=>'required|string|size:2','buyer.zip'=>'required|string|max:20',
                                'buyer.city'=>'required|string|max:100','buyer.address'=>'required|string|max:250',
                                'buyer.taxNumber'=>'nullable|string|max:30',
                            ])->validate();
                            app(Invoices::class)->issue(['paymentId'=>$payment['id'],'buyer'=>$buyer,'automatic'=>true]);
                            $counts['issued']++;
                        }catch(\Throwable $e){
                            $reason=$e instanceof \InvalidArgumentException?$e->getMessage():'A számlázás ellenőrzést igényel.';
                        }
                    }
                    if($reason)$counts['waiting']++;
                    app(SalonStore::class)->mutate(function(&$fresh)use($key,$reason){
                        $fresh['autoInvoiceAttempts'][$key]=now()->toISOString();
                        if($reason)$fresh['autoInvoiceIssues'][$key]=$reason;
                        else unset($fresh['autoInvoiceIssues'][$key]);
                    });
                }
            });
        }
        return $counts;
    }
}
