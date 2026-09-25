<?php
namespace App\Http\Controllers\Frontend;
use App\Http\Controllers\Controller;
use App\Support\LocalizedValidation;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PaymentMethod;
use App\Models\Product\ProductVariant;
use App\Services\BonusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CheckoutController extends Controller {
 public function index(){
  $addresses=auth()->user()->addresses()->orderByDesc('is_default')->latest()->get();
  $paymentMethods=PaymentMethod::where('active',1)->orderBy('sort_order')->get();
  return view('frontend.checkout',compact('addresses','paymentMethods'));
 }
 public function store(Request $request){
  $data=$request->validate([
   'cart'=>['required','array','min:1'],'cart.*.variant_id'=>['required','integer'],'cart.*.quantity'=>['required','integer','min:1','max:99'],
   'address_mode'=>['required',Rule::in(['existing','new'])],
   'address_id'=>['nullable','integer'],
   'title'=>['nullable','string','max:50'],'city'=>['nullable','string','max:100'],'district'=>['nullable','string','max:100'],'address'=>['nullable','string','max:500'],
   'building'=>['nullable','string','max:50'],'entrance'=>['nullable','string','max:50'],'floor'=>['nullable','string','max:30'],'apartment'=>['nullable','string','max:30'],'address_note'=>['nullable','string','max:1000'],
   'payment_method_id'=>['required','integer','exists:payment_methods,id'],'gift_wrap'=>['nullable','boolean'],'customer_note'=>['nullable','string','max:1500'],
  ], LocalizedValidation::messages(), LocalizedValidation::attributes());
  $customer=$request->user();
  return DB::transaction(function()use($data,$customer){
   $initialStatus=OrderStatus::where('code','new')->where('active',1)->firstOrFail();
   if($data['address_mode']==='existing'){
    $address=$customer->addresses()->findOrFail($data['address_id']);
   }else{
    validator($data,[
     'title'=>['required','string','max:50'],
     'city'=>['required'],
     'address'=>['required'],
    ],[
     'title.required'=>__('Ünvan adı daxil edilməlidir.'),
     'city.required'=>__('Şəhər daxil edilməlidir.'),
     'address.required'=>__('Küçə və ünvan daxil edilməlidir.'),
    ])->validate();
    $address=$customer->addresses()->create(['title'=>$data['title']??null,'city'=>$data['city'],'district'=>$data['district']??null,'address'=>$data['address'],'building'=>$data['building']??null,'entrance'=>$data['entrance']??null,'floor'=>$data['floor']??null,'apartment'=>$data['apartment']??null,'note'=>$data['address_note']??null,'is_default'=>$customer->addresses()->count()===0]);
   }
   $cart=collect($data['cart'])->keyBy('variant_id');
   $variants=ProductVariant::whereIn('id',$cart->keys())->where('active',1)->get();
   abort_if($variants->count()!==$cart->count(),422,__('Səbətdə mövcud olmayan məhsul var.'));
   $subtotal=0;$items=[];
   foreach($variants as $v){$qty=(int)$cart[$v->id]['quantity'];$line=round((float)$v->price*$qty,2);$subtotal+=$line;$items[]=['product_id'=>$v->product_id,'product_variant_id'=>$v->id,'unit_price'=>$v->price,'quantity'=>$qty,'total'=>$line];}
   $order=Order::create(['order_no'=>'PS'.now()->format('ymd').str_pad((string)((Order::max('id')??0)+1),6,'0',STR_PAD_LEFT),'customer_id'=>$customer->id,'customer_address_id'=>$address->id,'payment_method_id'=>$data['payment_method_id'],'source'=>'website','order_status_id'=>$initialStatus->id,'gift_wrap'=>(bool)($data['gift_wrap']??false),'customer_note'=>$data['customer_note']??null,'subtotal'=>$subtotal,'discount'=>0,'total'=>$subtotal]);
   $order->items()->createMany($items);
   app(BonusService::class)->earnForOrder($customer,$order,(float)$order->total);
   DB::table('order_status_logs')->insert(['order_id'=>$order->id,'status_id'=>$initialStatus->id,'created_at'=>now(),'updated_at'=>now()]);
   return response()->json(['ok'=>true,'order_no'=>$order->order_no,'redirect'=>route('checkout.success',$order)]);
  });
 }
 public function success(Order $order){abort_unless($order->customer_id===auth()->id(),403);return view('frontend.checkout-success',compact('order'));}
}
