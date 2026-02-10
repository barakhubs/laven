<?php
namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Models\EmailSMSTemplate;
use App\Models\PaymentGateway;
use App\Utilities\Installer;
use Database\Seeders\PaymentNotificationSeeder;
use Illuminate\Support\Facades\Artisan;
use ZipArchive;

class UpdateController extends Controller {

    private $updateFileName = 'credit-lite-update.zip';
    private $app_version    = '2.5';

    public function index($action = '') {
        if (!file_exists($this->updateFileName)) {
            return redirect('/');
        }

        if ($action == 'process') {
            $zip = new ZipArchive();
            $zip->open($this->updateFileName, ZipArchive::CREATE);
            $zip->deleteName('.env');
            $zip->close();

            $zip->open($this->updateFileName, ZipArchive::CREATE);
            $zip->extractTo(".");
            $zip->close();

            unlink($this->updateFileName);
            
            Artisan::call('migrate', ['--force' => true]);

            //Update Seeder
            $email_template = EmailSMSTemplate::where('slug', 'LOAN_PAYMENT_RECEIVED')->first();
            if (! $email_template) {
                Artisan::call('db:seed', ['--class' => PaymentNotificationSeeder::class, '--force' => true]);
            }

            //Remove BlockChain Gateway beacsue it's not working anymore
            $blockChain = PaymentGateway::where('slug', 'BlockChain')->first();
            if ($blockChain) {
                $blockChain->delete();
            }
    
            //Update Version Number
            Installer::updateEnv([
                'APP_VERSION' => $this->app_version,
            ]);
    
            update_option('APP_VERSION', $this->app_version);
            
            return redirect('migration/update');
        }

        $requirements = Installer::checkServerRequirements();
        return view('install.update', compact('requirements'));
    }

    public function update_migration() {
        Artisan::call('migrate', ['--force' => true]);

        //Update Seeder
        $email_template = EmailSMSTemplate::where('slug', 'LOAN_PAYMENT_RECEIVED')->first();
        if (! $email_template) {
            Artisan::call('db:seed', ['--class' => PaymentNotificationSeeder::class, '--force' => true]);
        }

        //Remove BlockChain Gateway beacsue it's not working anymore
        $blockChain = PaymentGateway::where('slug', 'BlockChain')->first();
        if ($blockChain) {
            $blockChain->delete();
        }

        //Update Version Number
        Installer::updateEnv([
            'APP_VERSION' => $this->app_version,
        ]);

        update_option('APP_VERSION', $this->app_version);

        return redirect()->route('login')->with('success', 'System has been updated to version ' . $this->app_version);
    }
}
