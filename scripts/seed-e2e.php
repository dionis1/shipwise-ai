<?php
// Runs only against the explicitly isolated E2E database. Never seed a production account.
require is_file('/app/vendor/autoload.php') ? '/app/vendor/autoload.php' : dirname(__DIR__).'/apps/backend/vendor/autoload.php';
$dsn = getenv('DATABASE_URL') ?: '';
if (!str_contains($dsn,'/shipwise_e2e')) { throw new RuntimeException('A dedicated shipwise_e2e database is required.'); }
$password = getenv('E2E_PASSWORD');
if (!$password || strlen($password) < 12) { throw new RuntimeException('Supply a generated E2E_PASSWORD.'); }
$db = Doctrine\DBAL\DriverManager::getConnection((new Doctrine\DBAL\Tools\DsnParser(['postgresql'=>'pdo_pgsql']))->parse($dsn));
$db->executeStatement('TRUNCATE users, audit_events CASCADE');
foreach (['SUPER_ADMIN','FINANCE_MANAGER','QA_ENGINEER','CARRIER_MANAGER','CUSTOMER'] as $role) {
 $id = App\Shared\Domain\Identifier::new();
 $db->insert('users',['id'=>$id,'email'=>strtolower($role).'@e2e.shipwise.test','name'=>ucwords(strtolower(str_replace('_',' ',$role))),'password_hash'=>password_hash($password,PASSWORD_BCRYPT),'kind'=>$role==='CUSTOMER'?'customer':'staff','status'=>'active','session_version'=>1,'created_at'=>gmdate('Y-m-d H:i:s')]);
 $db->insert('user_roles',['user_id'=>$id,'role_code'=>$role]);
}
