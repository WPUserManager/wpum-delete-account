import { writeFileSync } from 'fs';
import { wpCli, e2e, ensureHtaccess, setupWpumPages, STATE_FILE } from './fixtures';

async function globalSetup(): Promise<void> {
  console.log('\n[DA E2E] Running global setup...');

  wpCli('rewrite structure "/%postname%/"');
  ensureHtaccess();

  wpCli('plugin activate wp-user-manager');
  wpCli('plugin activate wpum-delete-account');

  // Notices on screen would break redirects.
  wpCli('config set WP_DEBUG_DISPLAY false --raw');

  setupWpumPages();

  const ids = e2e('setup');
  console.log(`[DA E2E] Seeded: ${ids}`);
  writeFileSync(STATE_FILE, ids);

  wpCli('rewrite flush');

  console.log('[DA E2E] Global setup complete!\n');
}

export default globalSetup;
