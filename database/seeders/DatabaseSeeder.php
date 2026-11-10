<?php

namespace Database\Seeders;

use App\Enums\WalletTransactionType;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ShippingMethod;
use App\Models\StoreSettings;
use App\Models\User;
use App\Support\Money;
use App\Wallet\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    /**
     * The catalog: the four legacy products, then the showcase additions.
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
        [
            'name' => 'Bananas',
            'slug' => 'bananas',
            'aisle' => 'fruit-and-veg',
            'price' => 129,
            'sku' => '50112840023417',
            'image' => 'bananas.jpg',
            'description' => 'A bunch of five ripe Cavendish bananas, picked green and ripened in store. Sweet, soft and ready to eat, or ideal for smoothies and banana bread.',
        ],
        [
            'name' => 'Avocados (pair)',
            'slug' => 'avocados',
            'aisle' => 'fruit-and-veg',
            'price' => 249,
            'sku' => '50112840031122',
            'image' => 'avocados.jpg',
            'description' => 'Two Hass avocados with creamy, buttery flesh. Ripen them in the fruit bowl for a day or two, then slice onto toast or mash into guacamole.',
        ],
        [
            'name' => 'Vine Tomatoes',
            'slug' => 'vine-tomatoes',
            'aisle' => 'fruit-and-veg',
            'price' => 189,
            'sku' => '50112840045390',
            'image' => 'vine-tomatoes.jpg',
            'description' => 'Sweet cherry tomatoes left to ripen on the vine for a fuller flavour. Roast them whole, toss them into a salad or eat them straight from the pack.',
        ],
        [
            'name' => 'Orange Juice 1L',
            'short_name' => 'Orange Juice',
            'slug' => 'orange-juice',
            'aisle' => 'drinks',
            'price' => 279,
            'sku' => '50112850012764',
            'image' => 'orange-juice.jpg',
            'description' => 'Freshly squeezed orange juice with bits, never from concentrate. Each litre holds the juice of around ten oranges, so keep it chilled and drink within five days of opening.',
        ],
        [
            'name' => 'Sparkling Water 6-pack',
            'short_name' => 'Sparkling Water',
            'slug' => 'sparkling-water',
            'aisle' => 'drinks',
            'price' => 349,
            'sku' => '50112850027818',
            'image' => 'sparkling-water.jpg',
            'description' => 'Six 500 ml bottles of lightly sparkling natural mineral water. Crisp and refreshing on its own, or with a slice of lemon.',
        ],
        [
            'name' => 'Whole Milk 2L',
            'short_name' => 'Whole Milk',
            'slug' => 'whole-milk',
            'aisle' => 'dairy',
            'price' => 159,
            'sku' => '50112860011905',
            'image' => 'whole-milk.jpg',
            'description' => 'Fresh whole milk from grass-fed cows, pasteurised and bottled within a day of milking. Rich and creamy in tea, coffee or a bowl of cereal.',
        ],
        [
            'name' => 'Greek Yogurt',
            'slug' => 'greek-yogurt',
            'aisle' => 'dairy',
            'price' => 229,
            'sku' => '50112860024356',
            'image' => 'greek-yogurt.jpg',
            'description' => 'Thick, strained Greek-style yogurt made with whole milk. Spoon it over granola and fruit, or stir it into curries and dips.',
        ],
        [
            'name' => 'Free-range Eggs (12)',
            'short_name' => 'Free-range Eggs',
            'slug' => 'free-range-eggs',
            'aisle' => 'dairy',
            'price' => 319,
            'sku' => '50112860038471',
            'image' => 'free-range-eggs.jpg',
            'description' => 'A dozen large free-range eggs from hens that roam outdoors every day. Deep orange yolks for baking, poaching and Sunday breakfasts.',
        ],
        [
            'name' => 'Sourdough Loaf',
            'slug' => 'sourdough-loaf',
            'aisle' => 'bakery',
            'price' => 349,
            'sku' => '50112870015230',
            'image' => 'sourdough-loaf.jpg',
            'description' => 'A slow-fermented sourdough loaf with a crackly crust and an open, chewy crumb. Baked fresh every morning from flour, water, salt and a long-loved starter.',
        ],
        [
            'name' => 'Croissants (4)',
            'short_name' => 'Croissants',
            'slug' => 'croissants',
            'aisle' => 'bakery',
            'price' => 299,
            'sku' => '50112870029645',
            'image' => 'croissants.jpg',
            'description' => 'Four all-butter croissants with flaky golden layers. Warm them in the oven for a few minutes before serving.',
        ],
        [
            'name' => 'Bagels (6)',
            'short_name' => 'Bagels',
            'slug' => 'bagels',
            'aisle' => 'bakery',
            'price' => 259,
            'sku' => '50112870033187',
            'image' => 'bagels.jpg',
            'description' => 'Six boiled-then-baked bagels topped with poppy and sesame seeds. Toast them and add cream cheese, or build a hearty sandwich.',
        ],
        [
            'name' => 'Blueberry Muffins (4)',
            'short_name' => 'Blueberry Muffins',
            'slug' => 'blueberry-muffins',
            'aisle' => 'bakery',
            'price' => 279,
            'sku' => '50112870047502',
            'image' => 'blueberry-muffins.jpg',
            'description' => 'Four soft muffins packed with whole blueberries and a crunchy sugar top. Baked in store and best enjoyed the same day.',
        ],
        [
            'name' => 'Extra Virgin Olive Oil',
            'short_name' => 'Olive Oil',
            'slug' => 'olive-oil',
            'aisle' => 'pantry',
            'price' => 649,
            'sku' => '50112880014429',
            'image' => 'olive-oil.jpg',
            'description' => 'Cold-pressed extra virgin olive oil from a single estate in southern Spain. Peppery and fruity, made for dressing salads and finishing dishes.',
        ],
        [
            'name' => 'Penne Pasta',
            'slug' => 'penne-pasta',
            'aisle' => 'pantry',
            'price' => 149,
            'sku' => '50112880028863',
            'image' => 'penne-pasta.jpg',
            'description' => 'Bronze-cut penne made from durum wheat semolina. The rough surface holds on to every bit of sauce, and it cooks in eleven minutes.',
        ],
        [
            'name' => 'Basmati Rice 1kg',
            'short_name' => 'Basmati Rice',
            'slug' => 'basmati-rice',
            'aisle' => 'pantry',
            'price' => 299,
            'sku' => '50112880032576',
            'image' => 'basmati-rice.jpg',
            'description' => 'Aged long-grain basmati rice that cooks up light, fluffy and fragrant. A kilo bag for curries, pilafs and everyday dinners.',
        ],
        [
            'name' => 'Wildflower Honey',
            'slug' => 'honey',
            'aisle' => 'pantry',
            'price' => 429,
            'sku' => '50112880046091',
            'image' => 'honey.jpg',
            'description' => 'Raw wildflower honey from a small local apiary, gently strained and never heated. Drizzle it on porridge or stir it into tea.',
        ],
        [
            'name' => 'Dark Chocolate',
            'slug' => 'dark-chocolate',
            'aisle' => 'snacks',
            'price' => 199,
            'sku' => '50112890013648',
            'image' => 'dark-chocolate.jpg',
            'description' => 'A 100 g bar of 70 percent dark chocolate made with single-origin cocoa. Smooth and rich, with notes of red berries.',
        ],
        [
            'name' => 'Sea Salt Crisps',
            'slug' => 'sea-salt-crisps',
            'aisle' => 'snacks',
            'price' => 179,
            'sku' => '50112890027083',
            'image' => 'sea-salt-crisps.jpg',
            'description' => 'Hand-cooked potato crisps seasoned with flaky sea salt. Thick-cut and extra crunchy, in a 150 g sharing bag.',
        ],
        [
            'name' => 'Mixed Nuts',
            'slug' => 'mixed-nuts',
            'aisle' => 'snacks',
            'price' => 449,
            'sku' => '50112890031796',
            'image' => 'mixed-nuts.jpg',
            'description' => 'A roasted mix of almonds, cashews, walnuts and hazelnuts with a few raisins. Lightly salted and good for snacking any time of day.',
        ],
        [
            'name' => 'Granola Bars (6)',
            'short_name' => 'Granola Bars',
            'slug' => 'granola-bars',
            'aisle' => 'snacks',
            'price' => 329,
            'sku' => '50112890045211',
            'image' => 'granola-bars.jpg',
            'description' => 'Six chewy oat bars with cranberries, seeds and a touch of honey. Each one is wrapped on its own for lunchboxes and walks.',
        ],
    ];

    public function run(): void
    {
        $this->seedCatalog();

        ShippingMethod::factory()->pickup()->create();
        ShippingMethod::factory()->ups()->create();

        $demo = User::factory()->create([
            'name' => 'Sam Rivera',
            'email' => 'demo@cartopia.test',
            'password' => '12345',
        ]);
        app(Wallet::class)->credit($demo, Money::fromCents(StoreSettings::DEFAULT_SIGNUP_CREDIT_CENTS), WalletTransactionType::SignupBonus);

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
        $aisleNames = [
            'fruit-and-veg' => 'Fruit and veg',
            'drinks' => 'Drinks',
            'dairy' => 'Dairy',
            'bakery' => 'Bakery',
            'pantry' => 'Pantry',
            'snacks' => 'Snacks',
        ];
        foreach ($aisleNames as $slug => $name) {
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
