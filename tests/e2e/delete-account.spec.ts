import {
  test,
  expect,
  state,
  member,
  createMember,
  postStatus,
  setRedirect,
  loginAs,
  wpAdminLogin,
  submitLoginForm,
  submitDeleteForm,
  PASSWORD,
} from './fixtures';

const FORM = '#wpum-submit-delete-account-form';

test.describe('The Delete account tab', () => {
  test('a visitor is sent to the login page and never sees the form', async ({ page, accountPage }) => {
    await page.goto(`${accountPage}delete-account`);

    await expect(page).toHaveURL(/\/wpum-login\//);
    await expect(page.locator(FORM)).toHaveCount(0);
  });

  test('a logged-in member gets the tab, and it opens the form', async ({ page, accountPage }) => {
    createMember('da_tab');
    await loginAs(page, 'da_tab');

    await page.goto(accountPage);
    await page.locator('.wpum-account-navigation a.tab-delete-account').click();

    await expect(page).toHaveURL(/\/wpum-account\/delete-account/);
    await expect(page.locator(FORM)).toBeVisible();
    await expect(page.locator('.wpum-delete-account-form h2')).toHaveText('Delete account');
    await expect(page.locator(`${FORM} input[name="password"]`)).toHaveAttribute('type', 'password');
  });
});

test.describe('Deleting an account', () => {
  test.afterEach(() => {
    setRedirect('none');
  });

  test('a member deletes their account through the form and can\'t log in again', async ({ page, accountPage }) => {
    const created = createMember('da_leaver', 'author');
    await loginAs(page, 'da_leaver');

    await page.goto(`${accountPage}delete-account`);
    const prompt = await submitDeleteForm(page, PASSWORD);

    expect(prompt).toContain('Are you sure you want to delete your account?');

    // Sent to the home page, logged out.
    await page.waitForURL((url) => url.pathname === '/');
    await expect(page.locator('body')).not.toHaveClass(/logged-in/);

    // The account has gone, and with it the login.
    expect(member('da_leaver')).toBeNull();
    await submitLoginForm(page, 'da_leaver');
    await expect(page.locator('#login_error')).toBeVisible();

    // Their post is in the trash.
    expect(postStatus(created.post)).toBe('trash');
  });

  test('the old session is no use once the account has gone', async ({ page, accountPage, context }) => {
    createMember('da_session');
    await loginAs(page, 'da_session');
    const cookies = await context.cookies();

    await page.goto(`${accountPage}delete-account`);
    await submitDeleteForm(page, PASSWORD);
    await page.waitForURL((url) => url.pathname === '/');

    // Replaying the cookies from before gets nowhere.
    await context.addCookies(cookies);
    await page.goto(accountPage);
    await expect(page).toHaveURL(/\/wpum-login\//);
  });

  test('a wrong password keeps the account', async ({ page, accountPage }) => {
    const created = createMember('da_wrong', 'author');
    await loginAs(page, 'da_wrong');

    await page.goto(`${accountPage}delete-account`);
    await submitDeleteForm(page, 'NotMyP@ssw0rd!');

    await expect(page.locator('.wpum-message.error')).toContainText('The password you entered is incorrect. Your account has not been deleted.');

    expect(member('da_wrong')).not.toBeNull();
    expect(postStatus(created.post)).toBe('publish');

    // Still logged in here, and the password still works.
    await expect(page.locator(FORM)).toBeVisible();
    await submitLoginForm(page, 'da_wrong');
    await expect(page.locator('#login_error')).toHaveCount(0);
  });

  test('saying no to the confirmation keeps the account', async ({ page, accountPage }) => {
    createMember('da_cancel');
    await loginAs(page, 'da_cancel');

    await page.goto(`${accountPage}delete-account`);
    const url = page.url();
    await submitDeleteForm(page, PASSWORD, 'dismiss');

    expect(page.url()).toBe(url);
    await expect(page.locator(FORM)).toBeVisible();
    expect(member('da_cancel')).not.toBeNull();
  });

  test('the member goes to the page chosen in the settings', async ({ page, accountPage }) => {
    setRedirect(state().pages.goodbye);
    createMember('da_redirect');
    await loginAs(page, 'da_redirect');

    await page.goto(`${accountPage}delete-account`);
    await submitDeleteForm(page, PASSWORD);

    await page.waitForURL(/\/sorry-to-see-you-go\//);
    await expect(page.getByText('Your account has been deleted.')).toBeVisible();
    expect(member('da_redirect')).toBeNull();
  });

  test('an administrator can delete their own account', async ({ page, accountPage }) => {
    createMember('da_admin', 'administrator');
    await loginAs(page, 'da_admin');

    await page.goto(`${accountPage}delete-account`);
    await submitDeleteForm(page, PASSWORD);

    await page.waitForURL((url) => url.pathname === '/');
    expect(member('da_admin')).toBeNull();
  });
});

test.describe('Settings', () => {
  test('After account cancellation is on the Redirects tab', async ({ page }) => {
    await wpAdminLogin(page);

    await page.goto('/wp-admin/users.php?page=wpum-settings#/redirects');

    await expect(page.getByText('After account cancellation')).toBeVisible({ timeout: 30000 });
  });
});
