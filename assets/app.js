(() => {
  const root = document.documentElement;
  const storedTheme = localStorage.getItem('blog-theme');
  const systemDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  const themeToggle = document.querySelector('[data-theme-toggle]');
  const themeIcon = document.querySelector('[data-theme-icon]');
  let editorInstance = null;
  const commentEditors = [];

  const resolvedTheme = () => root.dataset.theme || (systemDark ? 'dark' : 'light');
  const syncThemeIcon = () => {
    if (!themeIcon) return;
    themeIcon.textContent = resolvedTheme() === 'dark' ? 'light_mode' : 'dark_mode';
  };
  const applyTheme = theme => {
    root.dataset.theme = theme;
    localStorage.setItem('blog-theme', theme);
    syncThemeIcon();
    if (editorInstance && typeof editorInstance.setTheme === 'function') {
      editorInstance.setTheme(theme === 'dark' ? 'dark' : 'classic');
    }
    commentEditors.forEach(editor => {
      if (editor && typeof editor.setTheme === 'function') {
        editor.setTheme(theme === 'dark' ? 'dark' : 'classic');
      }
    });
  };

  if (storedTheme === 'dark' || storedTheme === 'light') {
    root.dataset.theme = storedTheme;
  }
  syncThemeIcon();
  themeToggle?.addEventListener('click', () => {
    applyTheme(resolvedTheme() === 'dark' ? 'light' : 'dark');
  });

  const snackbar = document.querySelector('[data-snackbar]');
  if (snackbar) {
    setTimeout(() => snackbar.classList.add('is-hiding'), 2000);
  }

  const announcement = document.querySelector('[data-announcement]');
  if (announcement) {
    const version = announcement.dataset.announcementVersion || '1';
    const key = `announcement-read-${version}`;
    if (localStorage.getItem(key) !== '1') {
      announcement.hidden = false;
    }
    announcement.querySelector('[data-announcement-close]')?.addEventListener('click', () => {
      announcement.hidden = true;
    });
    announcement.querySelector('[data-announcement-read]')?.addEventListener('click', () => {
      localStorage.setItem(key, '1');
      announcement.hidden = true;
    });
  }

  document.querySelectorAll('[data-dismissible-tip]').forEach(tip => {
    const key = `tip-closed-${tip.dataset.dismissibleTip}`;
    if (localStorage.getItem(key) === '1') {
      tip.hidden = true;
      return;
    }
    tip.querySelector('[data-tip-close]')?.addEventListener('click', () => {
      localStorage.setItem(key, '1');
      tip.hidden = true;
    });
  });

  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver(
      entries => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.12 }
    );
    document.querySelectorAll('.surface-enter').forEach(el => observer.observe(el));
  } else {
    document.querySelectorAll('.surface-enter').forEach(el => el.classList.add('is-visible'));
  }

  const rippleTargets = document.querySelectorAll(
    '.filled-btn, .tonal-btn, .text-btn, .icon-btn, .profile-chip, .site-card, .post-card, .stat-card, .admin-nav-section summary'
  );
  rippleTargets.forEach(element => {
    element.addEventListener('pointerdown', event => {
      const rect = element.getBoundingClientRect();
      const ripple = document.createElement('span');
      ripple.className = 'ripple';
      ripple.style.left = `${event.clientX - rect.left}px`;
      ripple.style.top = `${event.clientY - rect.top}px`;
      element.appendChild(ripple);
      ripple.addEventListener('animationend', () => ripple.remove(), { once: true });
    });
  });

  document.querySelectorAll('.post-card').forEach(card => {
    card.addEventListener('click', event => {
      if (event.target.closest('a, button, form')) {
        return;
      }
      card.classList.toggle('is-expanded');
    });
  });

  const adminTabs = document.querySelectorAll('[data-admin-tab]');
  const adminPanels = document.querySelectorAll('[data-admin-panel]');
  const activateAdminPanel = tabName => {
    adminTabs.forEach(tab => {
      tab.classList.toggle('active', tab.dataset.adminTab === tabName);
    });
    adminPanels.forEach(panel => {
      panel.classList.toggle('active', panel.dataset.adminPanel === tabName);
    });
  };
  adminTabs.forEach(tab => {
    tab.addEventListener('click', () => {
      localStorage.setItem('admin-tab', tab.dataset.adminTab);
      activateAdminPanel(tab.dataset.adminTab);
    });
  });
  const initialAdminTab = location.hash ? location.hash.slice(1) : localStorage.getItem('admin-tab');
  if (initialAdminTab && document.querySelector(`[data-admin-tab="${initialAdminTab}"]`)) {
    activateAdminPanel(initialAdminTab);
  }

  const adminDrawer = document.querySelector('[data-admin-drawer]');
  document.querySelector('[data-admin-drawer-toggle]')?.addEventListener('click', () => {
    adminDrawer?.classList.toggle('is-open');
  });
  adminTabs.forEach(tab => {
    tab.addEventListener('click', () => adminDrawer?.classList.remove('is-open'));
  });

  const commentsPanel = document.querySelector('[data-comments-panel]');
  const commentsToggle = document.querySelector('[data-comments-toggle]');
  commentsToggle?.addEventListener('click', () => {
    if (!commentsPanel) return;
    const willOpen = commentsPanel.hidden;
    commentsPanel.hidden = false;
    commentsPanel.classList.toggle('is-open', willOpen);
    commentsToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    commentsToggle.classList.toggle('is-active', willOpen);
    if (willOpen) {
      commentsPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
      window.setTimeout(() => window.dispatchEvent(new Event('resize')), 80);
      return;
    }
    commentsPanel.classList.remove('is-open');
    window.setTimeout(() => {
      commentsPanel.hidden = true;
    }, 180);
  });

  document.querySelectorAll('[data-open-user-modal]').forEach(button => {
    const dialog = document.getElementById(button.dataset.openUserModal);
    if (!dialog || typeof dialog.showModal !== 'function') return;
    button.addEventListener('click', () => dialog.showModal());
    dialog.addEventListener('click', event => {
      if (event.target === dialog) {
        dialog.close();
      }
    });
  });

  document.querySelectorAll('[data-ajax-like]').forEach(formEl => {
    formEl.addEventListener('submit', async event => {
      event.preventDefault();
      const button = formEl.querySelector('button');
      const count = formEl.querySelector('[data-like-count]');
      try {
        const response = await fetch(formEl.action, {
          method: 'POST',
          body: new FormData(formEl),
          headers: { 'X-Requested-With': 'fetch' },
        });
        const data = await response.json();
        if (count) count.textContent = data.likes;
        button?.classList.toggle('filled-btn', !!data.liked);
        button?.classList.toggle('tonal-btn', !data.liked);
      } catch (error) {
        formEl.submit();
      }
    });
  });

  const emailDialog = document.querySelector('[data-email-code-dialog]');
  const emailInput = emailDialog?.querySelector('[data-email-code-input]');
  const emailMessage = emailDialog?.querySelector('[data-email-code-message]');
  const emailConfirm = emailDialog?.querySelector('[data-email-code-confirm]');
  let pendingEmailForm = null;
  const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  const sendEmailCode = async formEl => {
    const emailField = formEl.querySelector('input[name="email"]');
    if (!emailField || !emailPattern.test(emailField.value.trim())) {
      if (emailMessage) emailMessage.textContent = '请先填写正确的邮箱地址。';
      emailDialog?.showModal();
      return false;
    }
    pendingEmailForm = formEl;
    const data = new FormData();
    data.set('csrf', formEl.querySelector('input[name="csrf"]')?.value || '');
    data.set('email', emailField.value.trim());
    data.set('context', formEl.dataset.emailContext || '');
    try {
      const response = await fetch('?action=send_email_code', { method: 'POST', body: data });
      const result = await response.json();
      if (emailMessage) emailMessage.textContent = result.message || '';
      if (emailInput) emailInput.value = '';
      emailDialog?.showModal();
      return !!result.ok;
    } catch (error) {
      if (emailMessage) emailMessage.textContent = '验证码发送失败，请检查邮件服务配置。';
      emailDialog?.showModal();
      return false;
    }
  };

  document.querySelectorAll('[data-email-verify-form]').forEach(formEl => {
    const emailField = formEl.querySelector('input[name="email"]');
    const sendButton = formEl.querySelector('[data-email-send-code]');
    if (!emailField || !sendButton) return;
    const syncSendButton = () => {
      sendButton.disabled = !emailPattern.test(emailField.value.trim());
    };
    emailField.addEventListener('input', syncSendButton);
    sendButton.addEventListener('click', () => sendEmailCode(formEl));
    syncSendButton();
  });

  document.querySelectorAll('[data-email-verify-form]').forEach(formEl => {
    formEl.addEventListener('submit', async event => {
      const emailField = formEl.querySelector('input[name="email"]');
      const codeField = formEl.querySelector('[data-email-code]');
      if (!emailField || !codeField || !emailField.value.trim() || codeField.value.trim()) return;
      if ((formEl.dataset.currentEmail || '') === emailField.value.trim()) return;
      event.preventDefault();
      if (formEl.querySelector('[data-email-send-code]')) {
        pendingEmailForm = formEl;
        if (emailMessage) emailMessage.textContent = '请输入邮箱验证码后再提交。';
        emailDialog?.showModal();
        return;
      }
      await sendEmailCode(formEl);
    });
  });

  emailConfirm?.addEventListener('click', () => {
    if (!pendingEmailForm || !emailInput?.value.trim()) return;
    const codeField = pendingEmailForm.querySelector('[data-email-code]');
    if (codeField) codeField.value = emailInput.value.trim();
    emailDialog?.close();
    pendingEmailForm.requestSubmit();
  });

  const avatarInput = document.querySelector('[data-avatar-file]');
  const avatarMode = document.querySelector('[data-avatar-mode]');
  const avatarUrlField = document.querySelector('[data-avatar-url-field]');
  const avatarUploadField = document.querySelector('[data-avatar-upload-field]');
  const cropper = document.querySelector('[data-avatar-cropper]');
  const canvas = document.querySelector('[data-avatar-canvas]');
  const zoomInput = document.querySelector('[data-avatar-zoom]');
  const croppedInput = document.querySelector('[data-avatar-cropped]');
  const cropDone = document.querySelector('[data-avatar-crop-done]');
  if (avatarMode && avatarUrlField && avatarUploadField) {
    const syncAvatarMode = () => {
      const upload = avatarMode.value === 'upload';
      avatarUploadField.hidden = !upload;
      avatarUrlField.hidden = upload;
    };
    avatarMode.addEventListener('change', syncAvatarMode);
    syncAvatarMode();
  }
  if (avatarInput && cropper && canvas && zoomInput && croppedInput) {
    const ctx = canvas.getContext('2d');
    const image = new Image();
    let loaded = false;
    const drawAvatar = () => {
      if (!loaded) return;
      const size = canvas.width;
      const zoom = Number(zoomInput.value || 1);
      ctx.clearRect(0, 0, size, size);
      ctx.save();
      ctx.beginPath();
      ctx.arc(size / 2, size / 2, size / 2, 0, Math.PI * 2);
      ctx.clip();
      const base = Math.max(size / image.width, size / image.height) * zoom;
      const width = image.width * base;
      const height = image.height * base;
      ctx.drawImage(image, (size - width) / 2, (size - height) / 2, width, height);
      ctx.restore();
      croppedInput.value = canvas.toDataURL('image/png');
    };
    avatarInput.addEventListener('change', () => {
      const file = avatarInput.files && avatarInput.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = event => {
        image.onload = () => {
          loaded = true;
          cropper.hidden = false;
          drawAvatar();
        };
        image.src = event.target.result;
      };
      reader.readAsDataURL(file);
    });
    zoomInput.addEventListener('input', drawAvatar);
    cropDone?.addEventListener('click', () => {
      drawAvatar();
      cropper.hidden = true;
    });
  }

  const renderVditorPreviews = () => {
    if (!window.Vditor || typeof Vditor.preview !== 'function') return;
    document.querySelectorAll('[data-vditor-preview]').forEach(block => {
      const source = block.querySelector('textarea');
      if (!source || block.dataset.rendered === '1') return;
      Vditor.preview(block, source.value || '', {
        cdn: 'assets/vendor/vditor',
        mode: resolvedTheme() === 'dark' ? 'dark' : 'light',
        hljs: { style: 'github' },
      });
      block.dataset.rendered = '1';
    });
  };

  const renderFallbackEditor = () => {
    target.innerHTML = '<textarea class="vditor-fallback" rows="18" placeholder="Write Markdown here..."></textarea>';
    const fallback = target.querySelector('textarea');
    fallback.value = output.value || '';
    fallback.addEventListener('input', () => {
      output.value = fallback.value;
    });
    form.addEventListener('submit', () => {
      output.value = fallback.value.trim();
    });
  };

  const target = document.getElementById('vditor-editor');
  const output = document.querySelector('[data-vditor-output]');
  const form = document.querySelector('.editor-form');
  if (target && output && form) {
    if (!window.Vditor) {
      renderFallbackEditor();
    } else {
      try {
      const editor = new Vditor('vditor-editor', {
      value: output.value || '',
      height: 560,
      minHeight: 420,
      mode: 'ir',
      lang: 'zh_CN',
      cdn: 'assets/vendor/vditor',
      placeholder: 'Write Markdown here. Headings, lists, quotes, code, images and links are supported.',
      cache: { enable: false },
      counter: { enable: true, type: 'markdown' },
      preview: {
        delay: 300,
        mode: 'both',
        hljs: { style: 'github' },
      },
      toolbarConfig: {
        pin: false,
      },
      toolbar: [
        'emoji',
        'headings',
        'bold',
        'italic',
        'strike',
        '|',
        'list',
        'ordered-list',
        'check',
        'quote',
        'line',
        'code',
        'inline-code',
        '|',
        'link',
        'table',
        'upload',
        '|',
        'undo',
        'redo',
        'fullscreen',
        'preview',
      ],
      upload: {
        accept: 'image/*',
        handler(files) {
          const file = files[0];
          if (!file) return null;
          const reader = new FileReader();
          reader.onload = event => {
            editor.insertValue(`![${file.name}](${event.target.result})`);
          };
          reader.readAsDataURL(file);
          return null;
        },
      },
      input(value) {
        output.value = value;
      },
      after() {
        output.value = editor.getValue();
        editorInstance = editor;
        if (resolvedTheme() === 'dark' && typeof editor.setTheme === 'function') {
          editor.setTheme('dark');
        }
      },
    });

      form.addEventListener('submit', () => {
        output.value = editor.getValue().trim();
      });
      } catch (error) {
        console.error('Vditor init failed:', error);
        renderFallbackEditor();
      }
    }
  }

  document.querySelectorAll('.vditor-comment-form').forEach((commentForm, index) => {
    const holder = commentForm.querySelector('[data-comment-editor]');
    const output = commentForm.querySelector('[data-comment-output]');
    if (!holder || !output) return;
    const id = `comment-editor-${index}`;
    holder.id = id;
    if (!window.Vditor) {
      holder.innerHTML = '<textarea class="vditor-fallback" rows="4"></textarea>';
      const fallback = holder.querySelector('textarea');
      fallback.addEventListener('input', () => output.value = fallback.value);
      commentForm.addEventListener('submit', () => output.value = fallback.value.trim());
      return;
    }
    const commentEditor = new Vditor(id, {
      height: holder.classList.contains('small') ? 180 : 240,
      minHeight: 160,
      mode: 'ir',
      lang: 'zh_CN',
      cdn: 'assets/vendor/vditor',
      cache: { enable: false },
      toolbar: ['emoji', 'bold', 'italic', 'link', 'quote', 'code', 'upload'],
      upload: {
        accept: 'image/*',
        handler(files) {
          const file = files[0];
          if (!file) return null;
          const reader = new FileReader();
          reader.onload = event => {
            commentEditor.insertValue(`![${file.name}](${event.target.result})`);
          };
          reader.readAsDataURL(file);
          return null;
        },
      },
      input(value) {
        output.value = value;
      },
      after() {
        output.value = commentEditor.getValue();
        if (resolvedTheme() === 'dark' && typeof commentEditor.setTheme === 'function') {
          commentEditor.setTheme('dark');
        }
      },
    });
    commentEditors.push(commentEditor);
    commentForm.addEventListener('submit', () => output.value = commentEditor.getValue().trim());
  });

  if (window.Vditor) {
    renderVditorPreviews();
  }
})();
