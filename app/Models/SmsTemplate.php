<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SmsTemplate extends Model
{
    public $timestamps = false;
    protected $fillable = ['code','name','template','active'];

    public function render(array $values = []): string
    {
        $message = $this->template;
        foreach ($values as $key => $value) $message = str_replace('{'.$key.'}', (string) $value, $message);
        return $message;
    }

    public static function message(string $code, array $values = []): ?string
    {
        $template = static::where('code',$code)->where('active',1)->first();
        return $template?->render($values);
    }
}
