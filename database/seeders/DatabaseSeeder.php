<?php

namespace Database\Seeders;

use App\Enums\WalletTransactionType;
use App\Models\Category;
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
     * @var list<array{name: string, short_name?: string, slug: string, aisle: string, price: int, sku: string, image: string, description: string}>
     */
    private const array PRODUCTS = [
        [
            'name' => 'Apple',
            'slug' => 'apple',
            'aisle' => 'fruit-and-veg',
            'price' => 30,
            'sku' => '63310304118428',
            'image' => 'apple.jpg',
            'description' => 'Delicious and crunchy, apple fruit is one of the most popular and favorite fruits among the health conscious, fitness lovers who firmly believe in the concept of "health is wealth." This wonderful fruit indeed packed with rich phytonutrients that in the real sense indispensable for optimal health and wellness. Certain antioxidants in apples have health promoting and disease prevention properties, and thereby, truly justifying the adage, "an apple a day keeps the doctor away."',
        ],
        [
            'name' => 'Monster Energy Drink',
            'short_name' => 'Monster Energy',
            'slug' => 'monster-energy-drink',
            'aisle' => 'drinks',
            'price' => 200,
            'sku' => '73310304118423',
            'image' => 'monster-energy-drink.png',
            'description' => 'Monster Energy is an energy drink launched in 2002 by Hansen Natural, now Monster Beverage Corporation of Corona, California. Each 16 fl oz (473 ml) can of the original green Monster holds 160 mg of caffeine, with taurine, panax ginseng, L-carnitine and B vitamins. Monster is well known for its black can and green clawed "M" logo.',
        ],
        [
            'name' => 'Water',
            'slug' => 'water',
            'aisle' => 'drinks',
            'price' => 100,
            'sku' => '93312304138923',
            'image' => 'water_bottle.jpg',
            'description' => 'Proudly made in Buxton in the heart of the Peak District, Buxton Water is a natural mineral water. Find out about us and get Naturally Pumped Up. Buxton® Natural Mineral Water is sourced in Buxton town, Derbyshire at St Ann\'s Spring, in the heart of the beautiful Peak District.',
        ],
        [
            'name' => 'Cheese Triangle',
            'slug' => 'cheese-triangle',
            'aisle' => 'dairy',
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

        $aisles = [];
        foreach (['fruit-and-veg' => 'Fruit and veg', 'drinks' => 'Drinks', 'dairy' => 'Dairy'] as $slug => $name) {
            $aisles[$slug] = Category::query()->create(['name' => $name, 'slug' => $slug]);
        }

        foreach (self::PRODUCTS as $data) {
            $product = Product::query()->create([
                'category_id' => $aisles[$data['aisle']]->id,
                'is_featured' => true,
                'name' => $data['name'],
                'short_name' => $data['short_name'] ?? null,
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
