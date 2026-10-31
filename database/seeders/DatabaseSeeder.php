<?php

namespace Database\Seeders;

use App\Enums\WalletTransactionType;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Support\Money;
use App\Wallet\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    /**
     * The legacy Cartopia catalog.
     *
     * @var list<array{name: string, slug: string, price: int, sku: string, image: string, description: string}>
     */
    private const array PRODUCTS = [
        [
            'name' => 'Apple',
            'slug' => 'apple',
            'price' => 30,
            'sku' => '63310304118428',
            'image' => 'apple.jpg',
            'description' => 'Delicious and crunchy, apple fruit is one of the most popular and favorite fruits among the health conscious, fitness lovers who firmly believe in the concept of "health is wealth." This wonderful fruit indeed packed with rich phytonutrients that in the real sense indispensable for optimal health and wellness. Certain antioxidants in apples have health promoting and disease prevention properties, and thereby, truly justifying the adage, "an apple a day keeps the doctor away."',
        ],
        [
            'name' => 'Beer',
            'slug' => 'beer',
            'price' => 200,
            'sku' => '73310304118423',
            'image' => 'heineken_beer.jpg',
            'description' => 'Heineken Lager Beer, or simply Heineken is a pale lager beer with 5% alcohol by volume produced by the Dutch brewing company Heineken International. Heineken is well known for its signature green bottle and red star.',
        ],
        [
            'name' => 'Water',
            'slug' => 'water',
            'price' => 100,
            'sku' => '93312304138923',
            'image' => 'water_bottle.jpg',
            'description' => 'Proudly made in Buxton in the heart of the Peak District, Buxton Water is a natural mineral water. Find out about us and get Naturally Pumped Up. Buxton® Natural Mineral Water is sourced in Buxton town, Derbyshire at St Ann\'s Spring, in the heart of the beautiful Peak District.',
        ],
        [
            'name' => 'Cheese Triangle',
            'slug' => 'cheese-triangle',
            'price' => 374,
            'sku' => '47729135118723',
            'image' => 'cheese-traingles.jpg',
            'description' => 'Arla Triangles Cheese is made from 100-percent natural ingredients.It offers a great cream taste and texture. Arla Triangles Cheese is also versatile and can be used for many different dishes and cakes.',
        ],
    ];

    public function run(): void
    {
        $this->seedCatalog();

        ShippingMethod::factory()->pickup()->create();
        ShippingMethod::factory()->ups()->create();

        $demo = User::factory()->create([
            'name' => 'amrcs92',
            'email' => 'amrcs1992@gmail.com',
            'password' => '12345',
        ]);
        app(Wallet::class)->credit($demo, Money::fromCents(10000), WalletTransactionType::SignupBonus);

        User::factory()->admin()->create([
            'name' => 'Admin',
            'email' => 'admin@cartopia.test',
            'password' => 'password',
        ]);
    }

    private function seedCatalog(): void
    {
        $disk = Storage::disk('public');

        foreach (self::PRODUCTS as $data) {
            $product = Product::query()->create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'],
                'price' => Money::fromCents($data['price']),
                'sku' => $data['sku'],
            ]);

            $path = 'products/'.$data['image'];
            $disk->put($path, (string) file_get_contents(database_path('seeders/images/'.$data['image'])));

            ProductImage::query()->create([
                'product_id' => $product->id,
                'path' => $path,
                'sort_order' => 0,
            ]);
        }
    }
}
