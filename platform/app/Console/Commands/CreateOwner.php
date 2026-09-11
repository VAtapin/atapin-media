<?php
namespace App\Console\Commands;
use App\Models\User;
use App\Models\Role;
use App\Services\Access;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
class CreateOwner extends Command
{
    protected $signature = 'platform:owner {email?}';
    protected $description = 'Create the first installation owner interactively';
    public function handle(Access $access): int
    {
        $email = $this->argument('email') ?: $this->ask('E-Mail');
        if (User::where('email', $email)->exists()) { $this->error('Account already exists; no changes made.'); return self::FAILURE; }
        $name = $this->ask('Name'); $password = $this->secret('Passwort');
        $repeat = $this->secret('Passwort wiederholen');
        $validator = Validator::make(compact('name','email','password'), ['name' => 'required|string|max:255',
            'email' => 'required|email|max:255', 'password' => 'required|string|max:72']);
        if ($validator->fails() || $password !== $repeat) { $this->error('Invalid input or passwords do not match.'); return self::FAILURE; }
        DB::transaction(function () use ($access, $name, $email, $password) {
            $access->seed(); $user = User::create(compact('name','email','password'));
            $user->roles()->attach(Role::where('name','Owner')->firstOrFail());
        });
        $this->info('Owner created. /login'); return self::SUCCESS;
    }
}
