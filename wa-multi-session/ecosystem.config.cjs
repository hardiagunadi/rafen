'use strict';

module.exports = {
  apps: [
    {
      name: 'wa-multi-session',
      script: 'gateway-server.cjs',
      cwd: '/var/www/rafen/wa-multi-session',
      output: '/var/www/rafen/storage/logs/wa-multi-session.log',
      error_file: '/var/www/rafen/storage/logs/wa-multi-session.log',
      time: true,
      env: {
        WA_MS_PORT: '3100',
        WA_MS_HOST: '127.0.0.1',
        WA_MS_AUTH_TOKEN: '',
        WA_MS_MASTER_KEY: '',
        WA_MS_DB_HOST: '127.0.0.1',
        WA_MS_DB_PORT: '3306',
        WA_MS_DB_NAME: 'rafen',
        WA_MS_DB_USER: 'rafen',
        WA_MS_DB_PASSWORD: '1ZpFklkkARjJohMbcSy1yQMP',
        WA_MS_DB_TABLE: 'wa_multi_session_auth_store',
        WA_MS_WEBHOOK_URL: 'https://hardiagunadi.web.id/webhook/wa',
      },
    },
  ],
};
