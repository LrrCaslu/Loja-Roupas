<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class ProductRequest extends FormRequest {
 public function authorize(): bool {return $this->user()?->can('manage',\App\Models\Product::class)??false;}
 public function rules(): array {
  $id=$this->route('product')?->id;
  return ['name'=>'required|string|max:180','sku'=>['required','max:80',Rule::unique('products','sku')->ignore($id)],'description'=>'nullable|string','details'=>'nullable|string','care'=>'nullable|string','price'=>'required|numeric|min:0|max:9999999','sale_price'=>'nullable|numeric|min:0|lt:price','category_id'=>'required|exists:categories,id','brand_id'=>'nullable|exists:brands,id','collection_id'=>'nullable|exists:collections,id','featured'=>'sometimes|boolean','is_new'=>'sometimes|boolean','active'=>'sometimes|boolean','images'=>'sometimes|array|max:8','images.*'=>'sometimes|image|mimes:jpg,jpeg,png,webp|max:4096','remove_images'=>'sometimes|array','remove_images.*'=>'integer|min:1','variants'=>'required|array|min:1','variants.*.size'=>'required|string|max:25','variants.*.color'=>'required|string|max:70','variants.*.stock'=>'required|integer|min:0|max:999999','variants.*.sku'=>'nullable|string|max:80'];
 }
}
