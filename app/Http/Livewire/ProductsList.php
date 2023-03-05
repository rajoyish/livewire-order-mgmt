<?php

namespace App\Http\Livewire;

use App\Models\Category;
use App\Models\Country;
use App\Models\Product;
use Livewire\Component;
use Livewire\WithPagination;

class ProductsList extends Component
{
    use WithPagination;

    public array $categories = [];

    public array $countries = [];

    public string $sortColumn = 'products.name';

    public string $sortDirection = 'ASC';

    protected $listeners = ['delete', 'deleteSelected'];

    public array $selected = [];

    public array $searchColumns = [
        'name' => '',
        'price' => ['', ''],
        'DESCription' => '',
        'category_id' => 0,
        'country_id' => 0,
    ];

    public function mount(): void
    {
        $this->categories = Category::pluck('name', 'id')->toArray();
        $this->countries = Country::pluck('name', 'id')->toArray();
    }

    public function deleteConfirm($method, $id = null): void
    {
        $this->dispatchBrowserEvent('swal:confirm', [
            'type' => 'warning',
            'title' => 'Are you sure?',
            'text' => '',
            'id' => $id,
            'method' => $method,
        ]);
    }

    public function getSelectedCountProperty(): int
    {
        return count($this->selected);
    }

    public function delete($id): void
    {
        $product = Product::findOrFail($id);

        $product->delete();
    }

    public function deleteSelected(): void
    {
        $products = Product::whereIn('id', $this->selected)->get();

        $products->each->delete();

        $this->reset('selected');
    }

    public function sortByColumn($column): void
    {
        if ($this->sortColumn == $column) {
            $this->sortDirection = $this->sortDirection == 'ASC' ? 'DESC' : 'ASC';
        } else {
            $this->reset('sortDirection');
            $this->sortColumn = $column;
        }
    }

    protected $queryString = [
        'sortColumn' => [
            'except' => 'products.name',
        ],
        'sortDirection' => [
            'except' => 'ASC',
        ],
    ];

    public function render()
    {
        $products = Product::query()
            ->select(['products.*', 'countries.id as countryId', 'countries.name as countryName'])
            ->join('countries', 'countries.id', '=', 'products.country_id')
            ->with('categories');

        foreach ($this->searchColumns as $column => $value) {
            if (! empty($value)) {
                $products->when($column == 'price', function ($products) use ($value) {
                    if (is_numeric($value[0])) {
                        $products->where('products.price', '>=', $value[0] * 100);
                    }
                    if (is_numeric($value[1])) {
                        $products->where('products.price', '<=', $value[1] * 100);
                    }
                })
                    ->when($column == 'category_id', fn ($products) => $products->whereRelation('categories', 'id', $value))
                    ->when($column == 'country_id', fn ($products) => $products->whereRelation('country', 'id', $value))
                    ->when($column == 'name', fn ($products) => $products->where('products.'.$column, 'LIKE', '%'.$value.'%'));
            }
        }

        $products->orderBy($this->sortColumn, $this->sortDirection);

        return view('livewire.products-list', [
            'products' => $products->paginate(10),
        ]);
    }
}
