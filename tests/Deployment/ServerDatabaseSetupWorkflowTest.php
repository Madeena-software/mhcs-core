<?php

declare(strict_types=1);

namespace Tests\Deployment;

use Tests\TestCase;

final class ServerDatabaseSetupWorkflowTest extends TestCase
{
    public function test_server_database_setup_scopes_existing_secrets_and_preserves_backup_contract(): void
    {
        $workflow = file_get_contents(base_path('.github/workflows/server-setup-db.yml'));

        $this->assertIsString($workflow);
        $this->assertSame(1, substr_count($workflow, 'workflow_dispatch:'));
        $this->assertStringNotContainsString('push:', $workflow);
        $this->assertStringNotContainsString('pull_request:', $workflow);
        $this->assertStringNotContainsString('schedule:', $workflow);
        $this->assertStringContainsString('runs-on: self-hosted', $workflow);

        $locateStart = strpos($workflow, '      - name: Locate DB container');
        $grantStart = strpos($workflow, '      - name: Fix app user grants');
        $backupStart = strpos($workflow, '      - name: Install S3 backup script and cron job');
        $this->assertNotFalse($locateStart);
        $this->assertNotFalse($grantStart);
        $this->assertNotFalse($backupStart);
        $this->assertLessThan($grantStart, $locateStart);
        $this->assertLessThan($backupStart, $grantStart);

        $locateStep = substr($workflow, $locateStart, $grantStart - $locateStart);
        $grantStep = substr($workflow, $grantStart, $backupStart - $grantStart);
        $backupStep = substr($workflow, $backupStart);

        $this->assertStringNotContainsString('${{ secrets.', $locateStep);
        foreach ([
            'com.docker.swarm.service.name=${APP_SLUG}_db',
            'name=${APP_SLUG}_db',
            'DB_CONTAINER=',
            'DB_CONTAINER=$DB_CONTAINER',
            'GITHUB_ENV',
            'FATAL: ${APP_SLUG}_db container is not running.',
        ] as $locateBehavior) {
            $this->assertStringContainsString($locateBehavior, $locateStep);
        }

        $grantEnvStart = strpos($grantStep, "        env:\n");
        $grantRunStart = strpos($grantStep, '        run:');
        $this->assertNotFalse($grantEnvStart);
        $this->assertNotFalse($grantRunStart);
        $this->assertSame(
            "        env:\n"
            ."          DB_DATABASE: \${{ secrets.DB_DATABASE }}\n"
            ."          DB_USERNAME: \${{ secrets.DB_USERNAME }}\n"
            ."          DB_PASSWORD: \${{ secrets.DB_PASSWORD }}\n"
            ."          DB_ROOT_PASSWORD: \${{ secrets.DB_ROOT_PASSWORD }}\n",
            substr($grantStep, $grantEnvStart, $grantRunStart - $grantEnvStart),
        );

        $backupEnvStart = strpos($backupStep, "        env:\n");
        $backupRunStart = strpos($backupStep, '        run:');
        $this->assertNotFalse($backupEnvStart);
        $this->assertNotFalse($backupRunStart);
        $this->assertSame(
            "        env:\n"
            ."          SUDO_PASSWORD: \${{ secrets.SUDO_PASSWORD }}\n"
            ."          APP_KEY: \${{ secrets.APP_KEY }}\n"
            ."          DB_DATABASE: \${{ secrets.DB_DATABASE }}\n"
            ."          DB_ROOT_PASSWORD: \${{ secrets.DB_ROOT_PASSWORD }}\n"
            ."          AWS_ACCESS_KEY_ID: \${{ secrets.AWS_ACCESS_KEY_ID }}\n"
            ."          AWS_SECRET_ACCESS_KEY: \${{ secrets.AWS_SECRET_ACCESS_KEY }}\n"
            ."          AWS_BUCKET: \${{ secrets.AWS_BUCKET }}\n"
            ."          AWS_ENDPOINT: \${{ secrets.AWS_ENDPOINT }}\n"
            ."          AWS_DEFAULT_REGION: \${{ secrets.AWS_DEFAULT_REGION }}\n",
            substr($backupStep, $backupEnvStart, $backupRunStart - $backupEnvStart),
        );

        foreach ([
            'FORCE_JAVASCRIPT_ACTIONS_TO_NODE24: true',
            'BACKUP_SCRIPT: /etc/madeena-mhcs_core-db-backup.sh',
            'BACKUP_ENV_FILE: /etc/madeena-mhcs_core-db-backup.env',
            'APP_SLUG: mhcs_core',
        ] as $globalSetting) {
            $this->assertStringContainsString($globalSetting, $workflow);
        }

        foreach ([
            'GRANT ALL PRIVILEGES ON',
            '${DB_DATABASE}',
            '.* TO',
            '\'${DB_USERNAME}\'@\'%\';',
            'FLUSH PRIVILEGES;',
            'mysql -u "${DB_USERNAME}" -p"${DB_PASSWORD}"',
            'USE',
            '${DB_DATABASE}',
            '; SHOW TABLES;',
        ] as $grantBehavior) {
            $this->assertStringContainsString($grantBehavior, $grantStep);
        }

        foreach ([
            'APP_KEY',
            'DB_DATABASE',
            'DB_ROOT_PASSWORD',
            'AWS_ACCESS_KEY_ID',
            'AWS_SECRET_ACCESS_KEY',
            'AWS_BUCKET',
            'AWS_ENDPOINT',
            'AWS_DEFAULT_REGION',
            'SUDO_PASSWORD',
        ] as $backupSetting) {
            $this->assertStringContainsString($backupSetting, $backupStep);
            $this->assertStringContainsString('${{ secrets.'.$backupSetting.' }}', $backupStep);
        }

        foreach ([
            'chmod 600 "$BACKUP_ENV_FILE"',
            'chmod 700 "$BACKUP_SCRIPT"',
            'mysqldump -u root -p"${DB_ROOT_PASS}"',
            '--single-transaction',
            '--routines',
            '--triggers',
            '--events',
            '--add-drop-table',
            'gzip > "$BACKUP_FILE"',
            'if [ ! -s "$BACKUP_FILE" ]; then',
            'gzip -t "$BACKUP_FILE"',
            "grep -c '^CREATE TABLE'",
            'minio/mc:latest',
            'mc alias set s3',
            'mc cp',
            '/backup/${BACKUP_NAME}',
            '${APP_SLUG}-backups/${BACKUP_NAME}',
            'RETENTION_DAYS="${RETENTION_DAYS:-14}"',
            'mc rm --recursive --force --older-than',
            '${RETENTION_DAYS}d',
            '${APP_SLUG}-backups/',
            '# madeena-mhcs_core-db-backup-start',
            'CRON_TZ=Asia/Jakarta',
            '0 2 * * * $BACKUP_SCRIPT >> /var/log/madeena-mhcs_core-db-backup.log 2>&1 # madeena-mhcs_core-db-backup',
            '# madeena-mhcs_core-db-backup-end',
        ] as $backupBehavior) {
            $this->assertStringContainsString($backupBehavior, $backupStep);
        }

        foreach (['CREATE DATABASE', 'DROP DATABASE', 'GRANT ALL PRIVILEGES ON *.*', 's3://new-', '0 3 * * *'] as $newSemantic) {
            $this->assertStringNotContainsString($newSemantic, $workflow);
        }
    }
}
