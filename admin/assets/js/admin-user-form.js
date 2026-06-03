(function () {
  "use strict";

  const form = document.querySelector("[data-admin-user-form]");
  if (!(form instanceof HTMLFormElement)) {
    return;
  }

  const endpoint = (form.dataset.apiEndpoint || "").trim();
  const userId = (form.dataset.userId || "").trim();
  const isSelfEdit = form.dataset.isSelfEdit === "1";
  const statusBox = document.querySelector("[data-admin-user-status]");
  const submitButton = form.querySelector("[data-submit-button]");
  const csrfInput = form.querySelector('input[name="csrf_token"]');

  const fields = {
    ad_soyad: form.querySelector('[name="ad_soyad"]'),
    eposta: form.querySelector('[name="eposta"]'),
    rutbe: form.querySelector('[name="rutbe"]'),
    sil: form.querySelector('[name="sil"]'),
    password: form.querySelector('[name="password"]'),
    password_confirm: form.querySelector('[name="password_confirm"]'),
    current_password: form.querySelector('[name="current_password"]'),
  };

  const fieldErrors = Object.fromEntries(
    Array.from(form.querySelectorAll("[data-field-error]")).map((node) => [node.getAttribute("data-field-error"), node]),
  );

  const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  function setStatus(type, message) {
    if (!(statusBox instanceof HTMLElement)) {
      return;
    }

    statusBox.className = `alert alert-${type}`;
    statusBox.textContent = message;
    statusBox.classList.remove("d-none");
  }

  function clearStatus() {
    if (!(statusBox instanceof HTMLElement)) {
      return;
    }

    statusBox.textContent = "";
    statusBox.className = "alert d-none";
  }

  function setFieldError(fieldName, message) {
    const input = fields[fieldName];
    const errorNode = fieldErrors[fieldName];

    if (input instanceof HTMLElement) {
      input.classList.add("is-invalid");
      input.setAttribute("aria-invalid", "true");
    }

    if (errorNode instanceof HTMLElement) {
      errorNode.textContent = message;
    }
  }

  function clearFieldError(fieldName) {
    const input = fields[fieldName];
    const errorNode = fieldErrors[fieldName];

    if (input instanceof HTMLElement) {
      input.classList.remove("is-invalid");
      input.removeAttribute("aria-invalid");
    }

    if (errorNode instanceof HTMLElement) {
      errorNode.textContent = "";
    }
  }

  function clearAllFieldErrors() {
    Object.keys(fieldErrors).forEach(clearFieldError);
  }

  function normalizeText(value) {
    return String(value || "").trim();
  }

  function validateForm() {
    clearAllFieldErrors();
    clearStatus();

    const payload = {
      ad_soyad: normalizeText(fields.ad_soyad && "value" in fields.ad_soyad ? fields.ad_soyad.value : ""),
      eposta: normalizeText(fields.eposta && "value" in fields.eposta ? fields.eposta.value : "").toLowerCase(),
      password: normalizeText(fields.password && "value" in fields.password ? fields.password.value : ""),
      password_confirm: normalizeText(fields.password_confirm && "value" in fields.password_confirm ? fields.password_confirm.value : ""),
      current_password: normalizeText(fields.current_password && "value" in fields.current_password ? fields.current_password.value : ""),
      rutbe: normalizeText(fields.rutbe && "value" in fields.rutbe ? fields.rutbe.value : ""),
      sil: normalizeText(fields.sil && "value" in fields.sil ? fields.sil.value : ""),
    };

    let hasError = false;

    if (payload.ad_soyad.length < 2 || payload.ad_soyad.length > 120 || /[<>]/u.test(payload.ad_soyad)) {
      setFieldError("ad_soyad", "Ad soyad 2-120 karakter araliginda olmali.");
      hasError = true;
    }

    if (!emailPattern.test(payload.eposta) || payload.eposta.length > 190) {
      setFieldError("eposta", "Gecerli bir e-posta adresi girin.");
      hasError = true;
    }

    if (payload.password !== "" && payload.password.length < 10) {
      setFieldError("password", "Sifre en az 10 karakter olmali.");
      hasError = true;
    }

    if ((payload.password !== "" || fields.password_confirm?.hasAttribute("required")) && payload.password !== payload.password_confirm) {
      setFieldError("password_confirm", "Sifre tekrari eslesmiyor.");
      hasError = true;
    }

    const sensitiveChangeRequested = isSelfEdit && (
      payload.password !== "" ||
      (fields.eposta instanceof HTMLInputElement && fields.eposta.defaultValue.trim().toLowerCase() !== payload.eposta)
    );

    if (sensitiveChangeRequested && payload.current_password === "") {
      setFieldError("current_password", "Hassas degisiklikler icin mevcut sifrenizi girin.");
      hasError = true;
    }

    if (hasError) {
      setStatus("danger", "Formu kontrol edip tekrar deneyin.");
      return null;
    }

    return payload;
  }

  function buildApiPayload(formState) {
    const payload = {
      ad_soyad: formState.ad_soyad,
      eposta: formState.eposta,
    };

    if (formState.password !== "") {
      payload.password = formState.password;
    }

    if (isSelfEdit && formState.current_password !== "") {
      payload.current_password = formState.current_password;
    }

    if (!isSelfEdit) {
      payload.rutbe = Number.parseInt(formState.rutbe, 10);
      payload.sil = Number.parseInt(formState.sil, 10);
    }

    return payload;
  }

  function applyUserData(data) {
    if (!data || typeof data !== "object") {
      return;
    }

    if (fields.ad_soyad instanceof HTMLInputElement) {
      fields.ad_soyad.value = String(data.ad_soyad || "");
      fields.ad_soyad.defaultValue = fields.ad_soyad.value;
    }

    if (fields.eposta instanceof HTMLInputElement) {
      fields.eposta.value = String(data.eposta || "");
      fields.eposta.defaultValue = fields.eposta.value;
    }

    if (fields.rutbe instanceof HTMLSelectElement && typeof data.rutbe !== "undefined") {
      fields.rutbe.value = String(data.rutbe);
    }

    if (fields.sil instanceof HTMLSelectElement && typeof data.sil !== "undefined") {
      fields.sil.value = String(data.sil);
    }
  }

  function setLoadingState(isLoading) {
    if (!(submitButton instanceof HTMLButtonElement)) {
      return;
    }

    submitButton.disabled = isLoading;
    submitButton.textContent = isLoading ? "Kaydediliyor..." : (userId ? "Kaydet" : "Kullanici Ekle");
  }

  async function loadUser() {
    if (endpoint === "" || userId === "") {
      return;
    }

    try {
      const response = await fetch(`${endpoint}?id=${encodeURIComponent(userId)}`, {
        method: "GET",
        credentials: "same-origin",
        headers: {
          Accept: "application/json",
        },
      });

      if (!response.ok) {
        throw new Error("load_failed");
      }

      const result = await response.json();
      if (!result || result.ok !== true || !result.data) {
        throw new Error("invalid_payload");
      }

      applyUserData(result.data);
    } catch (_error) {
      setStatus("warning", "Kullanici bilgileri su anda yenilenemedi.");
    }
  }

  form.addEventListener("submit", async function (event) {
    const formState = validateForm();
    if (!formState) {
      event.preventDefault();
      return;
    }

    if (endpoint === "" || userId === "") {
      return;
    }

    event.preventDefault();

    if (!(csrfInput instanceof HTMLInputElement) || csrfInput.value.trim() === "") {
      setStatus("danger", "Istek dogrulanamadi. Sayfayi yenileyip tekrar deneyin.");
      return;
    }

    // Oturum kimligi backend tarafinda HttpOnly Secure SameSite cookie ile dogrulanir.
    // Tokenlari localStorage/sessionStorage icinde saklamiyoruz.
    // Ucuncu taraf script/iframe eklenirse CSP, sandbox ve allowlist ayarlari ayrica degerlendirilmelidir.
    const payload = buildApiPayload(formState);
    setLoadingState(true);

    try {
      const response = await fetch(`${endpoint}?id=${encodeURIComponent(userId)}`, {
        method: "PATCH",
        credentials: "same-origin",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          "X-CSRF-Token": csrfInput.value,
        },
        body: JSON.stringify(payload),
      });

      let result = null;
      try {
        result = await response.json();
      } catch (_error) {
        result = null;
      }

      if (!response.ok || !result || result.ok !== true || !result.data) {
        const safeMessage = result && typeof result.message === "string"
          ? result.message
          : "Islem tamamlanamadi. Lutfen daha sonra tekrar deneyin.";
        setStatus("danger", safeMessage);
        return;
      }

      applyUserData(result.data);

      if (fields.password instanceof HTMLInputElement) {
        fields.password.value = "";
      }

      if (fields.password_confirm instanceof HTMLInputElement) {
        fields.password_confirm.value = "";
      }

      if (fields.current_password instanceof HTMLInputElement) {
        fields.current_password.value = "";
      }

      clearAllFieldErrors();
      setStatus("success", typeof result.message === "string" ? result.message : "Kullanici bilgileri guncellendi.");
    } catch (_error) {
      setStatus("danger", "Islem tamamlanamadi. Lutfen daha sonra tekrar deneyin.");
    } finally {
      setLoadingState(false);
    }
  });

  loadUser();
})();
