<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Support\Export\XlsxWriter;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Commerce\CommerceTestCase;

/** «ورود از اکسل»: a WooCommerce CSV or an .xlsx into the menu; preview first, re-uploads update. */
final class CatalogImportTest extends CommerceTestCase
{
    /** Shaped like a real WooCommerce export: BOM, a short last row, a variable product, a duplicate. */
    private const WOO = "\xEF\xBB\xBFType,Name,Categories,Regular price,Short description\n"
        ."simple,امپرسو,نوشیدنی گرم بر پایه قهوه,155000,\n"
        ."simple,لاته,نوشیدنی گرم بر پایه قهوه,\"175,000\",\n"
        ."simple,موهیتو,\"نوشیدنی سرد > موکتل\",۱۹۵۰۰۰,لیموترش نعنا سودا\n"
        ."variable,پیتزا,غذا,,\n"
        ."simple,لاته,نوشیدنی گرم بر پایه قهوه,175000,\n"
        ."simple,سالاد بی‌قیمت,سالاد,,\n"
        .'simple,تن ماهی رژیمی,دریایی,550000';

    /** @param  array<string, mixed>  $extra */
    private function send(string $path, UploadedFile $file, array $extra = []): TestResponse
    {
        return $this->post("/api/v1/catalog/import{$path}", ['file' => $file, ...$extra], [...$this->staffHeaders($this->owner, $this->tenant), 'Accept' => 'application/json']);
    }

    private function csv(string $content, string $name = 'products.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    public function test_a_woocommerce_export_is_previewed_without_changing_anything(): void
    {
        $data = $this->send('/preview', $this->csv(self::WOO))->assertOk()->json('data');

        $this->assertSame(['type' => 0, 'name' => 1, 'category' => 2, 'price' => 3, 'description' => 4], $data['columns']);
        // «لاته» is already on the menu with two sizes: kept as is, with a note about its price.
        $this->assertSame(['create' => 3, 'update' => 0, 'same' => 1, 'error' => 1, 'skip' => 2, 'total' => 7], $data['summary']);
        $byName = collect($data['rows'])->keyBy('name');
        $this->assertSame(1_950_000, $byName['موهیتو']['price']);            // Persian digits, toman → rial
        $this->assertSame(['نوشیدنی سرد', 'موکتل'], $byName['موهیتو']['category']);
        $this->assertSame(5_500_000, $byName['تن ماهی رژیمی']['price']);     // the short last row still reads
        $this->assertSame('skip', $byName['پیتزا']['status']);
        $this->assertSame('error', $byName['سالاد بی‌قیمت']['status']);
        $this->assertNotEmpty($byName['لاته']['problems']);
        $this->assertContains('نوشیدنی سرد > موکتل', $data['new_categories']);

        $this->assertSame(2, $this->inTenant($this->tenant, fn () => Product::query()->count())); // espresso + latte from the fixture only
    }

    public function test_import_creates_the_menu_and_a_second_upload_only_updates_prices(): void
    {
        $this->send('', $this->csv(self::WOO))->assertOk()->assertJsonPath('data.created', 3)->assertJsonPath('data.categories', 4);

        $this->inTenant($this->tenant, function (): void {
            $mojito = Product::query()->where('name', 'موهیتو')->with('variants.prices', 'categories')->sole();
            $this->assertSame(1_950_000, $mojito->variants->sole()->basePrice()?->amount);
            $this->assertSame('موکتل', $mojito->categories->sole()->name);
            $this->assertSame('نوشیدنی سرد', Category::query()->whereKey($mojito->categories->sole()->parent_id)->value('name'));
            $this->assertSame('لیموترش نعنا سودا', $mojito->description);
        });

        // Same file with a new price for one item: one update, nothing duplicated.
        $again = str_replace('155000', '165000', self::WOO);
        $plan = $this->send('/preview', $this->csv($again))->assertOk()->json('data.summary');
        $this->assertSame(1, $plan['update']);
        $this->assertSame(3, $plan['same']);
        $this->send('', $this->csv($again))->assertOk()->assertJsonPath('data.created', 0)->assertJsonPath('data.updated', 1);

        $this->inTenant($this->tenant, function (): void {
            $this->assertSame(1, Product::query()->where('name', 'امپرسو')->count());
            $this->assertSame(1_650_000, Product::query()->where('name', 'امپرسو')->with('variants.prices')->sole()->variants->sole()->basePrice()?->amount);
        });
    }

    public function test_the_template_is_an_xlsx_that_imports_as_is(): void
    {
        $response = $this->get('/api/v1/catalog/import/template', $this->staffHeaders($this->owner, $this->tenant))->assertOk();
        $this->assertStringContainsString('menu-template.xlsx', (string) $response->headers->get('content-disposition'));

        $bytes = XlsxWriter::build('منو', ['نام محصول', 'دسته‌بندی', 'قیمت (تومان)'], [['کیک هویج', 'کیک و دسر', '۱۲۰٬۰۰۰'], ['دمنوش', 'نوشیدنی گرم', 60000]]);
        $file = UploadedFile::fake()->createWithContent('menu.xlsx', $bytes);
        $data = $this->send('/preview', $file)->assertOk()->json('data');
        $this->assertSame(['name' => 0, 'category' => 1, 'price' => 2], $data['columns']);
        $this->assertSame(1_200_000, $data['rows'][0]['price']);
        $this->assertSame(2, $data['summary']['create']);
    }

    public function test_bad_files_and_permissions(): void
    {
        $this->send('/preview', UploadedFile::fake()->create('menu.pdf', 10, 'application/pdf'))->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->send('/preview', $this->csv('not,a,zip', 'menu.xlsx'))->assertUnprocessable()->assertJsonPath('code', 'catalog_import_unreadable');
        $this->send('/preview', $this->csv("Name,Price\n"))->assertUnprocessable()->assertJsonPath('code', 'catalog_import_empty');
        $this->send('', $this->csv("Foo,Bar\nx,1"))->assertUnprocessable()->assertJsonPath('code', 'catalog_import_columns');

        // The café may say which column is which when the titles are unusual; prices can be in rial.
        $this->send('/preview', $this->csv("Foo,Bar\nکیک,120000"), ['columns' => ['name' => 0, 'price' => 1], 'unit' => 'rial'])
            ->assertOk()->assertJsonPath('data.rows.0.price', 120000);

        $cashier = $this->addMember($this->tenant, $this->owner, 'cashier');
        $this->post('/api/v1/catalog/import/preview', ['file' => $this->csv(self::WOO)], [...$this->staffHeaders($cashier, $this->tenant), 'Accept' => 'application/json'])->assertForbidden();
    }
}
