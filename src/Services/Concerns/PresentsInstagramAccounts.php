<?php

namespace hexa_package_instagram\Services\Concerns;

use hexa_core\Services\CredentialService;
use hexa_package_browser_worker\Contracts\BrowserWorkerBridgeContract;
use hexa_package_instagram\Domains\Config\InstagramConfigRepository;

trait PresentsInstagramAccounts
{

    public function accountPresentation(array $account): array
    {
        $profile = (string) ($account['profile'] ?? '');

        return array_merge($account, [
            'password_configured' => $profile !== '' ? $this->hasPassword($profile) : false,
            'password_masked' => $profile !== '' ? $this->maskedPassword($profile) : '',
        ]);
    }

    public static function stateProbeJs(): string
    {
        return <<<'JS'
const bodyText = (document.body?.innerText || '').trim();
const bodyLower = bodyText.toLowerCase();
const isVisible = (node) => {
  if (!node) return false;
  const style = window.getComputedStyle(node);
  const rect = node.getBoundingClientRect();
  return style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0' && rect.width > 0 && rect.height > 0;
};
const inputs = Array.from(document.querySelectorAll('input')).filter(isVisible);
const visibleTextInputs = inputs.filter((node) => ['text', 'email', 'tel', 'search', ''].includes((node.type || '').toLowerCase()));
const visiblePasswordInputs = inputs.filter((node) => (node.type || '').toLowerCase() === 'password');
const visibleLoginIdentityInputs = visibleTextInputs.filter((node) => /username|email|phone|mobile|login/i.test([node.name, node.id, node.getAttribute('aria-label'), node.getAttribute('placeholder')].filter(Boolean).join(' ')));
const loginForm = visiblePasswordInputs.length > 0 && (visibleLoginIdentityInputs.length > 0 || /log into instagram|log in to instagram|forgot password/i.test(bodyLower));
const accountChooserDetected = /see everyday moments from your close friends|use another profile/i.test(bodyLower) && /\bcontinue\b/i.test(bodyText) && !loginForm;
const verificationRequired = /\/auth_platform\/codeentry/i.test(location.pathname)
  || /check your whatsapp messages|enter the code we sent to your whatsapp account|check your email|check your inbox|enter the code we sent to|confirmation code|security code|try another way/i.test(bodyLower);
const verificationChannel = verificationRequired
  ? (/whatsapp/i.test(bodyText)
      ? 'whatsapp'
      : (/check your email|check your inbox|email/i.test(bodyLower)
          ? 'email'
          : (/text message|sms/i.test(bodyLower) ? 'sms' : 'code')))
  : '';
const captchaRequired = /i.?m not a robot|recaptcha|security check|combat harmful conduct|maintain the integrity of our products|google.?s privacy policy|google.?s terms of use/i.test(bodyText) || Boolean(document.querySelector("iframe[src*=\"recaptcha\"], iframe[title*=\"recaptcha\" i]"));
const challenge = verificationRequired || captchaRequired || /challenge|checkpoint|two_factor|suspended/i.test(location.pathname + " " + bodyText);
const alerts = Array.from(document.querySelectorAll('[role="alert"]')).filter(isVisible).map((node) => (node.innerText || '').trim()).filter(Boolean);
const visibleButtons = Array.from(document.querySelectorAll('button')).filter(isVisible).map((node) => (node.innerText || '').trim()).filter(Boolean).slice(0, 20);
const avatarLinks = Array.from(document.querySelectorAll('a[href]')).map((node) => node.getAttribute('href') || '').filter(Boolean);
const authenticatedMarkers = avatarLinks.filter((href) => /^\/(accounts\/edit|direct\/inbox|explore\/|[A-Za-z0-9._]+\/?)$/.test(href));
const strongNavSelectors = [
  'a[href="/direct/inbox/"]',
  'a[href="/accounts/activity/"]',
  'a[href="/accounts/edit/"]',
  'svg[aria-label="Home"]',
  'svg[aria-label="Search"]',
  'svg[aria-label="Explore"]',
  'svg[aria-label="Reels"]',
  'svg[aria-label="Messenger"]',
  'svg[aria-label="Notifications"]',
  'svg[aria-label="Profile"]',
];
const strongNavMatches = strongNavSelectors.filter((selector) => document.querySelector(selector));
const loginCopyDetected = !accountChooserDetected && /log into instagram|log in to instagram|mobile number, username or email|forgot password|log in with facebook|create new account|sign up/i.test(bodyLower);
const storyViewerDetected = /^\/stories\//i.test(location.pathname) && /instagram/i.test(document.title) && !loginCopyDetected && !loginForm && !challenge;
const navTextLabels = ['home', 'reels', 'messages', 'search', 'explore', 'notifications', 'create', 'dashboard', 'profile'];
const navTextMatches = navTextLabels.filter((label) => new RegExp('\\b' + label + '\\b', 'i').test(bodyText));
const profileOwnerControls = /edit profile|view archive|share photos|professional dashboard|followers|following/i.test(bodyText) && /instagram/i.test(document.title + ' ' + bodyText);
const connected = (strongNavMatches.length > 0 || authenticatedMarkers.length >= 3 || storyViewerDetected || navTextMatches.length >= 4 || profileOwnerControls) && !loginForm && !challenge && !/\/accounts\/login/i.test(location.pathname);

return {
  url: location.href,
  path: location.pathname,
  title: document.title,
  login_form: loginForm,
  verification_required: verificationRequired,
  verification_channel: verificationChannel,
  captcha_required: captchaRequired,
  challenge,
  login_copy_detected: loginCopyDetected,
  account_chooser_detected: accountChooserDetected,
  alerts,
  visible_buttons: visibleButtons,
  authenticated_markers: authenticatedMarkers.slice(0, 12),
  auth_indicator_count: authenticatedMarkers.length,
  strong_nav_count: strongNavMatches.length,
  strong_nav_matches: strongNavMatches,
  story_viewer_detected: storyViewerDetected,
  nav_text_count: navTextMatches.length,
  nav_text_matches: navTextMatches,
  profile_owner_controls: profileOwnerControls,
  visible_text_inputs: visibleTextInputs.map((node) => node.getAttribute('aria-label') || node.getAttribute('placeholder') || node.name || 'text-input').slice(0, 8),
  visible_password_inputs: visiblePasswordInputs.length,
  body_excerpt: bodyText.slice(0, 1200),
  connected,
};
JS;
    }

    public static function dismissCookieBannerJs(): string
    {
        return <<<'JS'
(() => {
  const matches = [
    'Allow all cookies',
    'Allow all',
    'Accept all',
    'Allow essential and optional cookies',
    'Accept cookies',
  ];
  const buttons = Array.from(document.querySelectorAll('button'));
  const target = buttons.find((button) => {
    const text = (button.innerText || '').trim().toLowerCase();
    return matches.some((candidate) => text.includes(candidate.toLowerCase()));
  });
  if (target) {
    target.click();
    return { clicked: true, text: (target.innerText || '').trim() };
  }
  return { clicked: false };
})()
JS;
    }

    public static function dismissPostLoginPromptsJs(): string
    {
        return <<<'JS'
(() => {
  const matches = [
    'Not now',
    'Not Now',
    'Cancel',
    'Skip',
  ];
  const buttons = Array.from(document.querySelectorAll('button'));
  const clicked = [];
  for (const button of buttons) {
    const text = (button.innerText || '').trim();
    if (!text) continue;
    if (matches.some((candidate) => text.toLowerCase() === candidate.toLowerCase())) {
      button.click();
      clicked.push(text);
    }
  }
  return { clicked };
})()
JS;
    }


    public static function openExplicitLoginFormJs(): string
    {
        return <<<'JS'
return (() => {
  const isVisible = (node) => {
    if (!node) return false;
    const style = window.getComputedStyle(node);
    const rect = node.getBoundingClientRect();
    return style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0' && rect.width > 0 && rect.height > 0;
  };

  const bodyText = (document.body?.innerText || '').replace(/\s+/g, ' ').trim();
  const wanted = [
    'log in to another account',
    'login to another account',
    'use another account',
    'use another profile',
    'switch accounts',
    'not you',
  ];
  const blocked = ['continue', 'continue as'];
  const candidates = Array.from(document.querySelectorAll('button, [role=button], a, div, span'))
    .filter(isVisible)
    .map((node) => ({ node, text: (node.innerText || node.textContent || '').replace(/\s+/g, ' ').trim() }))
    .filter((row) => row.text);

  const target = candidates
    .filter((row) => {
      const text = row.text.toLowerCase();
      if (blocked.some((blockedText) => text === blockedText || text.startsWith(blockedText + ' '))) {
        return false;
      }
      return wanted.some((wantedText) => text.includes(wantedText));
    })
    .sort((left, right) => left.text.length - right.text.length)[0] || null;

  const clickTarget = target?.node?.closest('button, [role=button], a') || target?.node || null;
  const summary = {
    clicked: false,
    button_text: target ? target.text : '',
    click_target: clickTarget ? (clickTarget.tagName + (clickTarget.getAttribute('role') ? '[role=' + clickTarget.getAttribute('role') + ']' : '')) : '',
    visible_actions: candidates.map((row) => row.text).filter((text) => text.length <= 90).slice(0, 24),
    body_excerpt: bodyText.slice(0, 600),
  };

  if (clickTarget) {
    clickTarget.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, cancelable: true, view: window }));
    clickTarget.dispatchEvent(new MouseEvent('mouseup', { bubbles: true, cancelable: true, view: window }));
    clickTarget.click();
    summary.clicked = true;
  }

  return summary;
})()
JS;
    }

    public static function continueSavedAccountChooserJs(): string
    {
        return <<<'JS'
return ((args) => {
  const isVisible = (node) => {
    if (!node) return false;
    const style = window.getComputedStyle(node);
    const rect = node.getBoundingClientRect();
    return style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0' && rect.width > 0 && rect.height > 0;
  };

  const bodyText = (document.body?.innerText || '').trim();
  const bodyLower = bodyText.toLowerCase();
  const username = String(args?.username || '').replace(/^@+/, '').trim().toLowerCase();
  const clickableSelector = 'button, [role=button], a, div, span';
  const candidates = Array.from(document.querySelectorAll(clickableSelector))
    .filter(isVisible)
    .map((node) => ({ node, text: (node.innerText || node.textContent || '').replace(/\s+/g, ' ').trim() }))
    .filter((row) => row.text);
  const continueCandidate = candidates.find((row) => row.text.toLowerCase() === 'continue')
    || candidates.find((row) => /^continue$/i.test(row.text))
    || candidates.find((row) => /continue/i.test(row.text) && row.text.length <= 40);
  const chooserDetected = Boolean(continueCandidate) && /use another profile|create new account|see everyday moments from your close friends/i.test(bodyText);
  const profileDetected = username === '' || bodyLower.includes(username);
  const clickTarget = continueCandidate?.node?.closest('button, [role=button], a') || continueCandidate?.node || null;
  const summary = {
    detected: chooserDetected,
    profile_detected: profileDetected,
    clicked: false,
    button_text: continueCandidate ? continueCandidate.text : '',
    click_target: clickTarget ? (clickTarget.tagName + (clickTarget.getAttribute('role') ? '[role=' + clickTarget.getAttribute('role') + ']' : '')) : '',
    visible_actions: candidates.map((row) => row.text).filter((text) => text.length <= 80).slice(0, 18),
    body_excerpt: bodyText.slice(0, 500),
  };

  if (chooserDetected && profileDetected && clickTarget) {
    clickTarget.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, cancelable: true, view: window }));
    clickTarget.dispatchEvent(new MouseEvent('mouseup', { bubbles: true, cancelable: true, view: window }));
    clickTarget.click();
    summary.clicked = true;
  }

  return summary;
})(args)
JS;
    }

    public static function submitLoginFormJs(): string
    {
        return <<<'JS'
return ((args) => {
  const isVisible = (node) => {
    if (!node) return false;
    const style = window.getComputedStyle(node);
    const rect = node.getBoundingClientRect();
  return style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0' && rect.width > 0 && rect.height > 0;
  };

  const setNativeValue = (node, value) => {
    try {
      node.focus({ preventScroll: true });
    } catch (error) {
      node.focus();
    }
    const prototype = Object.getPrototypeOf(node);
    const descriptor = Object.getOwnPropertyDescriptor(prototype, 'value');
    if (descriptor?.set) {
      descriptor.set.call(node, value);
    } else {
      node.value = value;
    }
    for (const eventName of ['input', 'change', 'keyup', 'blur']) {
      node.dispatchEvent(new Event(eventName, { bubbles: true }));
    }
  };

  const allInputs = Array.from(document.querySelectorAll('input')).filter(isVisible).filter((node) => !node.disabled);
  const usernameInput = document.querySelector('input[name="username"]')
    || allInputs.find((node) => ['text', 'email', 'tel', 'search', ''].includes((node.type || '').toLowerCase()));
  const passwordInput = document.querySelector('input[name="password"]')
    || allInputs.find((node) => (node.type || '').toLowerCase() === 'password');

  const summary = {
    found_username: Boolean(usernameInput),
    found_password: Boolean(passwordInput),
    visible_inputs: allInputs.map((node) => ({
      type: node.type || '',
      name: node.name || '',
      aria: node.getAttribute('aria-label') || '',
      placeholder: node.getAttribute('placeholder') || '',
    })).slice(0, 10),
    submitted: false,
  };

  if (!passwordInput) {
    summary.reason = 'Could not find a visible Instagram password input.';
    return summary;
  }

  if (usernameInput) {
    setNativeValue(usernameInput, String(args?.username || ''));
  }
  setNativeValue(passwordInput, String(args?.password || ''));

  const buttons = Array.from(document.querySelectorAll('button')).filter(isVisible).filter((node) => !node.disabled);
  const submitButton = document.querySelector('button[type="submit"], input[type="submit"]')
    || buttons.find((node) => /log in|login|sign in/i.test((node.innerText || '').trim()))
    || allInputs.find((node) => (node.type || '').toLowerCase() === 'submit' && /log in|login|sign in/i.test((node.value || '').trim()));

  if (submitButton) {
    submitButton.click();
    summary.submitted = true;
    summary.submit_text = (submitButton.innerText || '').trim();
  } else if (usernameInput.form) {
    if (typeof usernameInput.form.requestSubmit === 'function') {
      usernameInput.form.requestSubmit();
    } else {
      usernameInput.form.submit();
    }
    summary.submitted = true;
    summary.submit_text = 'form_submit';
  } else {
    summary.reason = 'Login inputs were found, but no submit button or form was available.';
  }

  return summary;
})(args)
JS;
    }

    public static function submitVerificationCodeJs(): string
    {
        return <<<'JS'
((args) => {
  const isVisible = (node) => {
    if (!node) return false;
    const style = window.getComputedStyle(node);
    const rect = node.getBoundingClientRect();
  return style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0' && rect.width > 0 && rect.height > 0;
  };

  const setNativeValue = (node, value) => {
    const prototype = Object.getPrototypeOf(node);
    const descriptor = Object.getOwnPropertyDescriptor(prototype, 'value');
    if (descriptor?.set) {
      descriptor.set.call(node, value);
    } else {
      node.value = value;
    }
    for (const eventName of ['input', 'change', 'keyup']) {
      node.dispatchEvent(new Event(eventName, { bubbles: true }));
    }
  };

  const code = String(args?.code || '').trim();
  const visibleInputs = Array.from(document.querySelectorAll('input')).filter(isVisible).filter((node) => !node.disabled);
  const codeInput = document.querySelector('input[name="code"]')
    || visibleInputs.find((node) => /code/i.test((node.name || '') + ' ' + (node.getAttribute('aria-label') || '') + ' ' + (node.getAttribute('placeholder') || '')))
    || visibleInputs.find((node) => ['text', 'tel', 'number', ''].includes((node.type || '').toLowerCase()));

  const summary = {
    found_code_input: Boolean(codeInput),
    submitted: false,
    visible_inputs: visibleInputs.map((node) => ({
      type: node.type || '',
      name: node.name || '',
      aria: node.getAttribute('aria-label') || '',
      placeholder: node.getAttribute('placeholder') || '',
    })).slice(0, 10),
  };

  if (!codeInput) {
    summary.reason = 'Could not find a visible Instagram verification code input.';
    return summary;
  }

  setNativeValue(codeInput, code);

  const buttons = Array.from(document.querySelectorAll('button')).filter(isVisible).filter((node) => !node.disabled);
  const submitButton = document.querySelector('button[type="submit"]')
    || buttons.find((node) => /continue|confirm|submit|next/i.test((node.innerText || '').trim()));

  if (submitButton) {
    submitButton.click();
    summary.submitted = true;
    summary.submit_text = (submitButton.innerText || '').trim();
  } else if (codeInput.form) {
    if (typeof codeInput.form.requestSubmit === 'function') {
      codeInput.form.requestSubmit();
    } else {
      codeInput.form.submit();
    }
    summary.submitted = true;
    summary.submit_text = 'form_submit';
  } else {
    summary.reason = 'Verification code input was found, but no continue button or form was available.';
  }

  return summary;
})(args)
JS;
    }
}
