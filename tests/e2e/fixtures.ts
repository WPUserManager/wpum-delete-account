import { test as base, expect, Page } from '@playwright/test';
import { execSync } from 'child_process';
import { readFileSync } from 'fs';
import { join } from 'path';

export const STATE_FILE = join(__dirname, '.state.json');
export const PASSWORD = 'TestPass123!';

/**
 * Run a WP-CLI command via wp-env against the test site.
 */
export function wpCli(command: string): string {
  try {
    const result = execSync(`npx @wordpress/env run tests-cli wp ${command}`, {
      cwd: process.env.PLUGIN_DIR || process.cwd(),
      encoding: 'utf-8',
      timeout: 60000,
      stdio: ['pipe', 'pipe', 'pipe'],
    });
    return result.trim();
  } catch (error: any) {
    if (error.stdout) {
      return error.stdout.trim();
    }
    throw error;
  }
}

/**
 * Run one of the suite's own WP-CLI commands (tests/mu-plugins/wpumda-e2e.php) and return
 * its last line of output.
 */
export function e2e(command: string): string {
  const output = execSync(`npx @wordpress/env run tests-cli wp wpumda-e2e ${command}`, {
    cwd: process.env.PLUGIN_DIR || process.cwd(),
    encoding: 'utf-8',
    timeout: 60000,
    stdio: ['pipe', 'pipe', 'pipe'],
  });
  const lines = output.split('\n').map((line) => line.trim()).filter(Boolean);
  return lines.length ? lines[lines.length - 1] : '';
}

export type State = {
  pages: { goodbye: number };
};

/**
 * The IDs global setup created.
 */
export function state(): State {
  return JSON.parse(readFileSync(STATE_FILE, 'utf-8'));
}

/**
 * Create (or recreate) a member with PASSWORD and a post of their own.
 */
export function createMember(login: string, role = 'subscriber'): { id: number; post: number } {
  return JSON.parse(e2e(`create_member ${login} --role=${role}`));
}

/**
 * A member's ID and roles, or null once the account has gone.
 */
export function member(login: string): { id: number; roles: string[] } | null {
  return JSON.parse(e2e(`member ${login}`));
}

export function postStatus(id: number): string {
  return e2e(`post_status ${id}`);
}

/**
 * Set the After account cancellation setting to a page, or clear it.
 */
export function setRedirect(page: number | 'none'): void {
  e2e(`redirect ${page}`);
}

/**
 * Log in through wp-login.php. The cookies land in the page's browser context.
 */
export async function loginAs(page: Page, login: string, password = PASSWORD): Promise<void> {
  const response = await page.request.post('/wp-login.php', {
    form: {
      log: login,
      pwd: password,
      'wp-submit': 'Log In',
      redirect_to: '/',
      testcookie: '1',
    },
  });
  await response.dispose();
}

export async function wpAdminLogin(page: Page): Promise<void> {
  await loginAs(page, 'admin', 'password');
}

/**
 * Log in the way a person does, through the wp-login.php form, in a browser with no cookies
 * from earlier. Whether it worked is up to the test.
 */
export async function submitLoginForm(page: Page, login: string, password = PASSWORD): Promise<void> {
  await page.context().clearCookies();
  await page.goto('/wp-login.php');
  await page.locator('#user_login').fill(login);
  await page.locator('#user_pass').fill(password);
  await page.locator('#wp-submit').click();
  await page.waitForLoadState();
}

/**
 * Fill in the delete account form and press the button, answering the "are you sure?" prompt.
 * Returns the prompt's text.
 */
export async function submitDeleteForm(page: Page, password: string, answer: 'accept' | 'dismiss' = 'accept'): Promise<string> {
  await page.locator('#wpum-submit-delete-account-form input[name="password"]').fill(password);

  const dialog = new Promise<string>((resolve) => {
    page.once('dialog', async (prompt) => {
      const message = prompt.message();
      await prompt[answer]();
      resolve(message);
    });
  });

  await page.locator('input[name="submit_delete_account"]').click();

  return dialog;
}

/**
 * Ensure .htaccess exists for pretty permalinks.
 */
export function ensureHtaccess(): void {
  wpCli(
    `eval 'file_put_contents(ABSPATH . ".htaccess", "# BEGIN WordPress\\n<IfModule mod_rewrite.c>\\nRewriteEngine On\\nRewriteBase /\\nRewriteRule ^index\\\\.php$ - [L]\\nRewriteCond %{REQUEST_FILENAME} !-f\\nRewriteCond %{REQUEST_FILENAME} !-d\\nRewriteRule . /index.php [L]\\n</IfModule>\\n# END WordPress");'`
  );
}

/**
 * Set up the four core WPUM pages (login, register, account, profile).
 */
export function setupWpumPages(): void {
  const pages = [
    { slug: 'wpum-login', title: 'Login', shortcode: '[wpum_login_form]', option: 'login_page' },
    { slug: 'wpum-register', title: 'Register', shortcode: '[wpum_register]', option: 'registration_page' },
    { slug: 'wpum-account', title: 'Account', shortcode: '[wpum_account]', option: 'account_page' },
    { slug: 'wpum-profile', title: 'Profile', shortcode: '[wpum_profile]', option: 'profile_page' },
  ];

  for (const p of pages) {
    let pageId = wpCli(`post list --post_type=page --name="${p.slug}" --post_status=publish --field=ID`);

    if (!pageId?.match(/^\d+$/)) {
      pageId = wpCli(
        `post create --post_type=page --post_title="${p.title}" --post_name="${p.slug}" --post_status=publish --post_content='${p.shortcode}' --porcelain`
      );
    }

    // Stored as a string, the way WPUM's settings panel saves page IDs.
    wpCli(`eval 'wpum_update_option("${p.option}", array("${pageId.trim()}"));'`);
  }
}

export const test = base.extend<{
  loginPage: string;
  accountPage: string;
}>({
  loginPage: '/wpum-login/',
  accountPage: '/wpum-account/',
});

export { expect };
