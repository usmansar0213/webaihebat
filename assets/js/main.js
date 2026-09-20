/**
* Template Name: Arsha
* Template URL: https://bootstrapmade.com/arsha-free-bootstrap-html-template-corporate/
* Updated: Feb 22 2025 with Bootstrap v5.3.3
* Author: BootstrapMade.com
* License: https://bootstrapmade.com/license/
*/

(function() {
  "use strict";

  /**
   * Apply .scrolled class to the body as the page is scrolled down
   */
  function toggleScrolled() {
    const selectBody = document.querySelector('body');
    const selectHeader = document.querySelector('#header');
    if (!selectHeader.classList.contains('scroll-up-sticky') && !selectHeader.classList.contains('sticky-top') && !selectHeader.classList.contains('fixed-top')) return;
    window.scrollY > 100 ? selectBody.classList.add('scrolled') : selectBody.classList.remove('scrolled');
  }

  document.addEventListener('scroll', toggleScrolled);
  window.addEventListener('load', toggleScrolled);

  /**
   * Mobile nav toggle
   */
  const mobileNavToggleBtn = document.querySelector('.mobile-nav-toggle');

  function mobileNavToogle() {
    document.querySelector('body').classList.toggle('mobile-nav-active');
    mobileNavToggleBtn.classList.toggle('bi-list');
    mobileNavToggleBtn.classList.toggle('bi-x');
  }
  if (mobileNavToggleBtn) {
    mobileNavToggleBtn.addEventListener('click', mobileNavToogle);
  }

  /**
   * Hide mobile nav on same-page/hash links
   */
  document.querySelectorAll('#navmenu a').forEach(navmenu => {
    navmenu.addEventListener('click', () => {
      if (document.querySelector('.mobile-nav-active')) {
        mobileNavToogle();
      }
    });

  });

  /**
   * Toggle mobile nav dropdowns
   */
  document.querySelectorAll('.navmenu .toggle-dropdown').forEach(navmenu => {
    navmenu.addEventListener('click', function(e) {
      e.preventDefault();
      this.parentNode.classList.toggle('active');
      this.parentNode.nextElementSibling.classList.toggle('dropdown-active');
      e.stopImmediatePropagation();
    });
  });

  /**
   * Preloader
   */
  const preloader = document.querySelector('#preloader');
  function hidePreloader() {
    if (!preloader) return;
    preloader.classList.add('is-hidden');
    preloader.style.opacity = '0';
    preloader.style.visibility = 'hidden';
    preloader.style.pointerEvents = 'none';
    setTimeout(() => {
      if (preloader.isConnected) {
        preloader.remove();
      }
    }, 200);
  }

  if (preloader) {
    document.addEventListener('DOMContentLoaded', hidePreloader);
    window.addEventListener('load', hidePreloader);
    window.addEventListener('pageshow', hidePreloader);
  }

  /**
   * Scroll top button
   */
  let scrollTop = document.querySelector('.scroll-top');

  function toggleScrollTop() {
    if (scrollTop) {
      window.scrollY > 100 ? scrollTop.classList.add('active') : scrollTop.classList.remove('active');
    }
  }
  scrollTop.addEventListener('click', (e) => {
    e.preventDefault();
    window.scrollTo({
      top: 0,
      behavior: 'smooth'
    });
  });

  window.addEventListener('load', toggleScrollTop);
  document.addEventListener('scroll', toggleScrollTop);

  /**
   * Animation on scroll function and init
   */
  function aosInit() {
    if (typeof AOS !== 'undefined') {
      AOS.init({
        duration: 400,
        easing: 'ease-in-out',
        once: true,
        mirror: false
      });
    }
  }
  window.addEventListener('load', () => {
    setTimeout(aosInit, 100);
  });

  /**
   * Initiate glightbox
   */
  if (typeof GLightbox !== 'undefined') {
    const glightbox = GLightbox({
      selector: '.glightbox'
    });
  }

  /**
   * Init swiper sliders
   */
  function initSwiper() {
    if (typeof Swiper === 'undefined') return;
    document.querySelectorAll('.init-swiper').forEach(function(swiperElement) {
      const configElement = swiperElement.querySelector('.swiper-config');
      if (!configElement) return;
      let config = JSON.parse(configElement.innerHTML.trim());

      if (swiperElement.classList.contains('swiper-tab')) {
        if (typeof initSwiperWithCustomPagination === 'function') {
          initSwiperWithCustomPagination(swiperElement, config);
        }
      } else {
        new Swiper(swiperElement, config);
      }
    });
  }

  window.addEventListener('load', () => {
    setTimeout(initSwiper, 150);
  });

  /**
   * Frequently Asked Questions Toggle
   */
  document.querySelectorAll('.faq-item h3, .faq-item .faq-toggle').forEach((faqItem) => {
    faqItem.addEventListener('click', () => {
      faqItem.parentNode.classList.toggle('faq-active');
    });
  });

  /**
   * Animate the skills items on reveal
   */
  if (typeof Waypoint !== 'undefined') {
    let skillsAnimation = document.querySelectorAll('.skills-animation');
    skillsAnimation.forEach((item) => {
      new Waypoint({
        element: item,
        offset: '80%',
        handler: function() {
          let progress = item.querySelectorAll('.progress .progress-bar');
          progress.forEach(el => {
            el.style.width = el.getAttribute('aria-valuenow') + '%';
          });
        }
      });
    });
  }

  /**
   * Init isotope layout and filters
   */
  document.querySelectorAll('.isotope-layout').forEach(function(isotopeItem) {
    let layout = isotopeItem.getAttribute('data-layout') ?? 'masonry';
    let filter = isotopeItem.getAttribute('data-default-filter') ?? '*';
    let sort = isotopeItem.getAttribute('data-sort') ?? 'original-order';

    if (typeof Isotope !== 'undefined' && typeof imagesLoaded !== 'undefined') {
      let initIsotope;
      imagesLoaded(isotopeItem.querySelector('.isotope-container'), function() {
        initIsotope = new Isotope(isotopeItem.querySelector('.isotope-container'), {
          itemSelector: '.isotope-item',
          layoutMode: layout,
          filter: filter,
          sortBy: sort
        });
      });

      isotopeItem.querySelectorAll('.isotope-filters li').forEach(function(filters) {
        filters.addEventListener('click', function() {
          isotopeItem.querySelector('.isotope-filters .filter-active').classList.remove('filter-active');
          this.classList.add('filter-active');
          if (initIsotope) {
            initIsotope.arrange({
              filter: this.getAttribute('data-filter')
            });
          }
          if (typeof aosInit === 'function') {
            aosInit();
          }
        }, false);
      });
    }

  });

  /**
   * Correct scrolling position upon page load for URLs containing hash links.
   */
  window.addEventListener('load', function(e) {
    if (window.location.hash) {
      if (document.querySelector(window.location.hash)) {
        setTimeout(() => {
          let section = document.querySelector(window.location.hash);
          let scrollMarginTop = getComputedStyle(section).scrollMarginTop;
          window.scrollTo({
            top: section.offsetTop - parseInt(scrollMarginTop),
            behavior: 'smooth'
          });
        }, 100);
      }
    }
  });

  /**
   * Navmenu Scrollspy
   */
  let navmenulinks = document.querySelectorAll('.navmenu a');

  /**
 * Scrollspy untuk navigasi menu (highlight menu aktif berdasarkan scroll)
 */
function navmenuScrollspy() {
  const scrollY = window.scrollY + 200;

  navmenulinks.forEach(navLink => {
    if (!navLink.hash) return;

    const section = document.querySelector(navLink.hash);
    if (!section) return;

    const sectionTop = section.offsetTop;
    const sectionBottom = sectionTop + section.offsetHeight;

    if (scrollY >= sectionTop && scrollY <= sectionBottom) {
      document.querySelectorAll('.navmenu a.active').forEach(activeLink => {
        activeLink.classList.remove('active');
      });
      navLink.classList.add('active');
    } else {
      navLink.classList.remove('active');
    }
  });
}

window.addEventListener('load', navmenuScrollspy);
document.addEventListener('scroll', navmenuScrollspy);

/**
 * Dropdown menu level dua (submenu dalam menu "Lainnya")
 */
document.querySelectorAll('.navmenu .dropdown .dropdown .toggle-dropdown').forEach(dropToggle => {
  dropToggle.addEventListener('click', function (e) {
    e.preventDefault();
    const parentLi = this.parentElement;
    const subDropdown = this.nextElementSibling;

    parentLi.classList.toggle('active');
    if (subDropdown) {
      subDropdown.classList.toggle('dropdown-active');
    }
    e.stopPropagation();
  });
});

/**
 * Theme toggle with local persistence
 */
function initThemeToggle() {
  const headerContainer = document.querySelector('#header .container-fluid');
  if (!headerContainer || document.querySelector('.theme-toggle-btn')) return;

  const storageKey = 'aihebat-theme';
  const btn = document.createElement('button');
  btn.type = 'button';
  btn.className = 'theme-toggle-btn';
  btn.innerHTML = '<i class="bi bi-moon-stars"></i><span>Tema</span>';
  btn.setAttribute('aria-label', 'Ganti tema');

  const applyTheme = (theme) => {
    document.body.setAttribute('data-theme', theme);
    btn.innerHTML = theme === 'dark'
      ? '<i class="bi bi-sun"></i><span>Terang</span>'
      : '<i class="bi bi-moon-stars"></i><span>Gelap</span>';
  };

  let savedTheme = 'light';
  try {
    savedTheme = localStorage.getItem(storageKey) || 'light';
  } catch (error) {
    savedTheme = 'light';
  }

  applyTheme(savedTheme === 'dark' ? 'dark' : 'light');

  btn.addEventListener('click', () => {
    const nextTheme = document.body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    applyTheme(nextTheme);
    try {
      localStorage.setItem(storageKey, nextTheme);
    } catch (error) {
      // Storage can fail in some browser privacy modes.
    }
  });

  headerContainer.appendChild(btn);
}

/**
 * Blog search, filter, sort, and pagination
 */
function initBlogInteractivity() {
  if (!document.body.classList.contains('blog-page')) return;

  const postCards = Array.from(document.querySelectorAll('#blog-posts .row.gy-4 > .col-lg-6'));
  const searchInput = document.getElementById('blog-search-input');
  const sortSelect = document.getElementById('blog-sort-select');
  const filterButtons = Array.from(document.querySelectorAll('.blog-filter-chip'));
  const resultMeta = document.getElementById('blog-results-meta');
  const emptyState = document.getElementById('blog-empty-state');
  const paginationRoot = document.querySelector('#pagination ul');
  const pageButtons = Array.from(document.querySelectorAll('#pagination .page-btn'));
  const prevButton = document.querySelector('#pagination .page-nav[data-action="prev"]');
  const nextButton = document.querySelector('#pagination .page-nav[data-action="next"]');

  if (!postCards.length || !searchInput || !sortSelect || !filterButtons.length || !paginationRoot) return;

  const state = {
    page: 1,
    query: '',
    category: 'all',
    sort: 'latest'
  };

  const pageSize = 4;

  const getDateValue = (card) => {
    const timeElement = card.querySelector('time');
    if (!timeElement) return 0;
    const dateValue = timeElement.getAttribute('datetime') || timeElement.textContent || '';
    const parsed = Date.parse(dateValue);
    return Number.isNaN(parsed) ? 0 : parsed;
  };

  const getSearchText = (card) => {
    const title = card.querySelector('.title')?.textContent || '';
    const excerpt = card.querySelector('.content p')?.textContent || '';
    const author = card.querySelector('.meta-top .bi-person')?.parentElement?.textContent || '';
    return `${title} ${excerpt} ${author}`.toLowerCase();
  };

  const getFilteredPosts = () => {
    let items = postCards.filter((card) => {
      const categoryMatch = state.category === 'all' || card.dataset.category === state.category;
      const textMatch = !state.query || getSearchText(card).includes(state.query);
      return categoryMatch && textMatch;
    });

    if (state.sort === 'latest') {
      items = items.sort((a, b) => getDateValue(b) - getDateValue(a));
    } else if (state.sort === 'oldest') {
      items = items.sort((a, b) => getDateValue(a) - getDateValue(b));
    } else if (state.sort === 'title') {
      items = items.sort((a, b) => {
        const titleA = a.querySelector('.title')?.textContent.trim().toLowerCase() || '';
        const titleB = b.querySelector('.title')?.textContent.trim().toLowerCase() || '';
        return titleA.localeCompare(titleB);
      });
    }

    return items;
  };

  const renderPagination = (totalPages) => {
    pageButtons.forEach((btn) => {
      const btnPage = parseInt(btn.getAttribute('data-page'), 10);
      btn.style.display = btnPage <= totalPages ? '' : 'none';
      btn.classList.toggle('active', btnPage === state.page);
    });

    if (prevButton) {
      prevButton.classList.toggle('disabled', state.page <= 1);
    }

    if (nextButton) {
      nextButton.classList.toggle('disabled', state.page >= totalPages);
    }
  };

  const render = () => {
    const filtered = getFilteredPosts();
    const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
    state.page = Math.min(state.page, totalPages);

    postCards.forEach((card) => {
      card.style.display = 'none';
    });

    const start = (state.page - 1) * pageSize;
    const visibleItems = filtered.slice(start, start + pageSize);

    visibleItems.forEach((card) => {
      card.style.display = '';
    });

    if (resultMeta) {
      resultMeta.textContent = `${filtered.length} artikel ditemukan.`;
    }

    if (emptyState) {
      emptyState.style.display = filtered.length ? 'none' : 'block';
    }

    renderPagination(totalPages);
  };

  const setPage = (pageNumber) => {
    if (!Number.isFinite(pageNumber) || pageNumber < 1) return;
    state.page = pageNumber;
    render();
    document.getElementById('blog-posts')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  searchInput.addEventListener('input', (event) => {
    state.query = event.target.value.trim().toLowerCase();
    state.page = 1;
    render();
  });

  sortSelect.addEventListener('change', (event) => {
    state.sort = event.target.value;
    state.page = 1;
    render();
  });

  filterButtons.forEach((button) => {
    button.addEventListener('click', () => {
      state.category = button.dataset.category || 'all';
      state.page = 1;
      filterButtons.forEach((item) => item.classList.remove('active'));
      button.classList.add('active');
      render();
    });
  });

  pageButtons.forEach((button) => {
    button.addEventListener('click', (event) => {
      event.preventDefault();
      const page = parseInt(button.getAttribute('data-page'), 10);
      if (Number.isFinite(page)) setPage(page);
    });
  });

  if (prevButton) {
    prevButton.addEventListener('click', (event) => {
      event.preventDefault();
      if (state.page > 1) setPage(state.page - 1);
    });
  }

  if (nextButton) {
    nextButton.addEventListener('click', (event) => {
      event.preventDefault();
      const filteredCount = getFilteredPosts().length;
      const totalPages = Math.max(1, Math.ceil(filteredCount / pageSize));
      if (state.page < totalPages) setPage(state.page + 1);
    });
  }

  window.goToPage = setPage;
  window.nextPage = () => setPage(state.page + 1);
  window.previousPage = () => setPage(state.page - 1);

  render();
}

/**
 * AI readiness assessment scoring
 */
function initAssessmentInteractivity() {
  if (!document.body.classList.contains('assessment-page')) return;

  const form = document.getElementById('ai-readiness-form');
  const questionList = document.getElementById('assessment-question-list');
  const progressSteps = document.getElementById('assessment-progress-steps');
  const progressLabel = document.getElementById('assessment-progress-label');
  const leadForm = document.getElementById('assessment-lead-form');
  const leadError = document.getElementById('assessment-lead-error');
  const saveStatus = document.getElementById('assessment-save-status');
  const nameInput = document.getElementById('assessment-name');
  const companyInput = document.getElementById('assessment-company');
  const emailInput = document.getElementById('assessment-email');
  const prevButton = document.getElementById('assessment-prev');
  const nextButton = document.getElementById('assessment-next');
  const submitButton = document.getElementById('assessment-submit');
  const result = document.getElementById('assessment-result');
  const overallScore = document.getElementById('assessment-overall-score');
  const overallLevel = document.getElementById('assessment-overall-level');
  const overallStatus = document.getElementById('assessment-overall-status');
  const readinessChart = document.getElementById('assessment-readiness-chart');
  const readinessPercent = document.getElementById('assessment-readiness-percent');
  const pillarResults = document.getElementById('assessment-pillar-results');
  const recommendation = document.getElementById('assessment-recommendation');
  const whatsappLink = document.getElementById('assessment-wa-link');
  const emailLink = document.getElementById('assessment-email-link');
  const pdfButton = document.getElementById('assessment-pdf-btn');
  const resetButton = document.getElementById('assessment-reset');
  const modeButtons = Array.from(document.querySelectorAll('.assessment-mode-btn'));
  const modeChip = document.getElementById('assessment-mode-chip');
  const countChip = document.getElementById('assessment-count-chip');
  const introText = document.getElementById('assessment-intro-text');
  const scaleLabels = Array.from(document.querySelectorAll('.assessment-scale span'));
  const companyLabel = document.querySelector('label[for="assessment-company"]');
  const goalInput = document.getElementById('assessment-goal');
  const goalField = document.querySelector('.assessment-goal-field');
  const followupTextElement = document.getElementById('assessment-followup-text');
  const resultKicker = document.querySelector('.assessment-result-summary .result-kicker');

  if (!form || !questionList || !progressSteps || !progressLabel || !leadForm || !leadError || !saveStatus || !nameInput || !companyInput || !emailInput || !prevButton || !nextButton || !submitButton || !result || !overallScore || !overallLevel || !overallStatus || !readinessChart || !readinessPercent || !pillarResults || !recommendation || !whatsappLink || !emailLink || !pdfButton) return;

  const organizationSections = [
    { id: 'strategy', title: 'Strategi & Leadership', icon: 'bi-compass', recommendation: 'Perjelas arah AI, sponsor eksekutif, KPI bisnis, dan prioritas use case bernilai tinggi.', questions: ['Apakah organisasi memiliki visi atau arah penggunaan AI yang jelas?', 'Apakah pimpinan secara aktif menggunakan atau mendukung penggunaan AI?', 'Apakah ada pemilik atau sponsor eksekutif untuk program AI?', 'Apakah investasi AI dikaitkan dengan tujuan bisnis yang terukur?', 'Apakah organisasi memilih use case AI berdasarkan nilai, risiko, data, dan dampak strategis?'] },
    { id: 'people', title: 'People & Capability', icon: 'bi-people', recommendation: 'Bangun literasi AI, pelatihan berbasis peran, kemampuan internal, dan program perubahan yang terarah.', questions: ['Seberapa luas pemahaman dasar AI di organisasi?', 'Apakah tersedia pelatihan AI untuk karyawan?', 'Apakah organisasi memiliki kemampuan AI seperti product management, data, governance, security, dan change management?', 'Bagaimana organisasi menangani resistensi terhadap AI?', 'Apakah pengguna bisnis dilibatkan dalam pengembangan solusi AI?'] },
    { id: 'process', title: 'Process & Use Case', icon: 'bi-diagram-3', recommendation: 'Buat proses formal untuk memilih, menguji, mengukur, mengubah, dan menghentikan use case AI.', questions: ['Apakah organisasi memiliki proses formal untuk mengidentifikasi use case AI?', 'Apakah AI sudah digunakan dalam proses bisnis inti?', 'Apakah organisasi mengukur manfaat AI dengan KPI, baseline, outcome, atau ROI?', 'Apakah proses bisnis disesuaikan setelah AI diterapkan?', 'Apakah organisasi memiliki proses penghentian use case AI yang tidak efektif atau terlalu berisiko?'] },
    { id: 'data-tech', title: 'Data & Technology', icon: 'bi-database-check', recommendation: 'Rapikan data utama, tetapkan standar kualitas, siapkan platform, integrasi API, dan versioning artefak AI.', questions: ['Apakah data organisasi cukup tersedia untuk use case AI?', 'Bagaimana kualitas data organisasi?', 'Apakah organisasi memiliki klasifikasi data?', 'Apakah infrastruktur mendukung eksperimen dan produksi AI?', 'Apakah integrasi AI menggunakan API dan arsitektur yang disetujui?'] },
    { id: 'governance', title: 'Governance, Security & Risk', icon: 'bi-shield-check', recommendation: 'Tetapkan kebijakan AI, klasifikasi risiko, kontrol data sensitif, validasi output, audit trail, dan incident response.', questions: ['Apakah organisasi memiliki kebijakan penggunaan AI?', 'Apakah use case AI diklasifikasikan berdasarkan risiko?', 'Apakah organisasi melindungi data sensitif saat menggunakan AI?', 'Apakah output AI divalidasi sebelum digunakan untuk keputusan penting?', 'Apakah penggunaan AI memiliki audit trail dan proses incident response?'] }
  ];

  const personalSections = [
    { id: 'personal-use', title: 'Pemanfaatan Harian', icon: 'bi-lightning-charge', recommendation: 'Mulai jadikan AI sebagai pendamping kerja harian untuk menulis, mencari ide, menganalisis bahan kerja, dan membuat materi.', questions: ['Saya menggunakan AI untuk membantu pekerjaan saya.', 'Saya menggunakan AI untuk membuat atau memperbaiki email, laporan, atau dokumen.', 'Saya menggunakan AI untuk mencari ide, brainstorming, atau menyusun rencana kerja.', 'Saya menggunakan AI untuk menganalisis data seperti Excel, tabel, laporan, dan sejenisnya.', 'Saya menggunakan AI untuk membuat presentasi atau materi visual.'] },
    { id: 'personal-habit', title: 'Kebiasaan & Produktivitas', icon: 'bi-repeat', recommendation: 'Perkuat kebiasaan prompting, iterasi jawaban, variasi tool, dan pengukuran waktu yang benar-benar dihemat oleh AI.', questions: ['Saya mulai memiliki prompt favorit atau template prompt yang sering digunakan.', 'Saya selalu memperbaiki hasil AI dengan memberikan instruksi lanjutan atau follow-up prompt.', 'Saya menggunakan lebih dari satu AI seperti ChatGPT, Claude, Gemini, Copilot, Perplexity, dan sejenisnya.', 'Saya menghemat waktu kerja minimal 30 menit setiap hari berkat AI.', 'Saya mulai mengubah cara kerja saya karena adanya AI, bukan hanya menggunakannya sesekali.'] },
    { id: 'personal-integration', title: 'Integrasi & Inovasi', icon: 'bi-rocket-takeoff', recommendation: 'Naikkan penggunaan AI dari produktivitas pribadi menuju otomasi, workflow, agent, dan inovasi bernilai baru.', questions: ['Saya pernah mengajarkan atau merekomendasikan penggunaan AI kepada rekan kerja.', 'Saya menghubungkan AI dengan aplikasi lain seperti Excel, Power BI, Notion, Zapier, n8n, Google Workspace, Microsoft 365, dan sejenisnya.', 'Saya menggunakan AI untuk mengotomatisasi sebagian pekerjaan yang sebelumnya dilakukan secara manual.', 'Saya pernah membuat workflow, chatbot, AI Agent, atau sistem kerja berbasis AI.', 'Saya sedang memikirkan atau sudah membuat produk, layanan, atau inovasi baru yang memanfaatkan AI.'] }
  ];

  const trainingEvaluationSections = [
    {
      id: 'training-value',
      title: 'Manfaat Pelatihan',
      icon: 'bi-stars',
      recommendation: 'Perkuat relevansi materi dengan konteks kerja peserta dan perbanyak contoh implementasi praktis.',
      questions: [
        'Secara keseluruhan, bagaimana Anda menilai pelatihan AI yang Anda ikuti?',
        'Seberapa relevan materi pelatihan dengan pekerjaan Anda?',
        'Seberapa besar pelatihan ini meningkatkan pemahaman Anda tentang AI dan pemanfaatannya?',
        'Setelah mengikuti pelatihan, seberapa yakin Anda dapat mulai menggunakan AI dalam pekerjaan?',
        'Seberapa besar pelatihan ini membantu Anda menemukan peluang penggunaan AI dalam pekerjaan?'
      ]
    },
    {
      id: 'training-facilitator',
      title: 'Kualitas Fasilitator',
      icon: 'bi-person-workspace',
      recommendation: 'Pertahankan kualitas fasilitator dan tambah sesi interaktif untuk studi kasus peserta.',
      questions: [
        'Bagaimana Anda menilai penguasaan materi oleh pelatih/fasilitator?',
        'Seberapa jelas dan mudah dipahami cara pelatih menyampaikan materi?',
        'Seberapa baik pelatih menghubungkan materi dengan kebutuhan dan pekerjaan peserta?',
        'Bagaimana kemampuan pelatih dalam menjawab pertanyaan dan membantu peserta saat mengalami kesulitan?',
        'Secara keseluruhan, bagaimana Anda menilai kualitas pelatih/fasilitator?'
      ]
    },
    {
      id: 'training-followup',
      title: 'Tindak Lanjut & Masukan',
      icon: 'bi-chat-left-text',
      recommendation: 'Gunakan umpan balik peserta untuk menyempurnakan tempo, kurikulum, dan topik lanjutan yang paling dibutuhkan.',
      questions: [
        {
          text: 'Bagaimana tempo penyampaian materi selama pelatihan?',
          type: 'choice',
          options: ['Terlalu lambat', 'Tepat', 'Terlalu cepat']
        },
        {
          text: 'Bagian atau materi apa yang paling bermanfaat bagi Anda?',
          type: 'textarea'
        },
        {
          text: 'Setelah mengikuti pelatihan ini, apa yang ingin Anda coba lakukan dengan AI dalam pekerjaan Anda?',
          type: 'textarea'
        },
        {
          text: 'Seberapa besar kemungkinan Anda merekomendasikan pelatihan ini kepada rekan kerja atau unit lain?',
          type: 'scale10'
        },
        {
          text: 'Apa yang menurut Anda perlu diperbaiki atau ditingkatkan dari pelatihan ini?',
          type: 'textarea'
        },
        {
          text: 'Materi atau kemampuan AI apa yang ingin Anda pelajari lebih lanjut?',
          type: 'textarea'
        }
      ]
    }
  ];

  const assessmentConfigs = {
    organization: {
      label: 'Organisasi',
      countLabel: '5 Pilar | 25 Pertanyaan',
      intro: 'Jawab setiap pertanyaan dengan skala 1 sampai 5. Hasil akan menampilkan maturity organisasi per pilar dan skor keseluruhan.',
      sectionLabel: 'Pilar',
      scoreLabel: 'Skor Kesiapan AI',
      statusPrefix: 'Status',
      resultNoun: 'organisasi',
      companyLabel: 'Perusahaan / Organisasi',
      companyRequired: true,
      followupTitle: 'Tingkatkan Maturity AI Organisasi Anda',
      followupText: 'Gunakan hasil assessment ini sebagai bahan diskusi awal dengan tim AIHebat untuk menyusun langkah peningkatan yang lebih terarah.',
      scales: ['1 = Belum ada', '2 = Baru mulai', '3 = Ada sebagian', '4 = Sudah berjalan', '5 = Matang dan konsisten'],
      sections: organizationSections,
      sectionScoreType: 'average',
      maxScore: 5,
      levelFromScore(score) {
        if (score < 1.8) return { label: 'Level 1 - Awareness', className: 'level-low' };
        if (score < 2.6) return { label: 'Level 2 - Exploratory', className: 'level-watch' };
        if (score < 3.4) return { label: 'Level 3 - Developing', className: 'level-mid' };
        if (score < 4.2) return { label: 'Level 4 - Ready with Conditions', className: 'level-good' };
        return { label: 'Level 5 - AI Ready', className: 'level-strong' };
      },
      scoreFormatter: (score) => score.toFixed(1),
      percent: (score) => Math.round((score / 5) * 100)
    },
    personal: {
      label: 'Pribadi',
      countLabel: 'Behavior Assessment | 15 Pertanyaan',
      intro: 'Jawab berdasarkan perilaku yang benar-benar sudah dilakukan. Hasil akan menunjukkan posisi Anda pada piramida Aware, Explore, Accelerate, Optimize, hingga Transform.',
      sectionLabel: 'Area',
      scoreLabel: 'Skor AI Readiness Pribadi',
      statusPrefix: 'Level Piramida',
      resultNoun: 'pribadi',
      companyLabel: 'Perusahaan / Organisasi (opsional)',
      companyRequired: false,
      followupTitle: 'Tingkatkan Kematangan Penggunaan AI Anda',
      followupText: 'Gunakan hasil assessment ini sebagai bahan awal untuk memilih fokus latihan dan workflow AI yang paling relevan.',
      scales: ['1 = Belum Pernah', '2 = Pernah Mencoba', '3 = Kadang-kadang', '4 = Sering', '5 = Sudah Menjadi Kebiasaan'],
      sections: personalSections,
      sectionScoreType: 'sum',
      maxScore: 75,
      levelFromScore(score) {
        if (score <= 24) return { label: 'Aware', className: 'level-low', description: 'Baru mengenal AI dan masih sebatas memahami potensinya.' };
        if (score <= 39) return { label: 'Explore', className: 'level-watch', description: 'Sudah mulai mencoba AI untuk berbagai tugas sederhana.' };
        if (score <= 54) return { label: 'Accelerate', className: 'level-mid', description: 'AI telah menjadi alat produktivitas yang digunakan secara rutin.' };
        if (score <= 67) return { label: 'Optimize', className: 'level-good', description: 'AI sudah terintegrasi dalam proses kerja dan membantu efisiensi.' };
        return { label: 'Transform', className: 'level-strong', description: 'AI menjadi bagian dari strategi inovasi dan penciptaan nilai baru.' };
      },
      sectionLevelFromScore(score) {
        const normalized = (score / 25) * 75;
        return this.levelFromScore(normalized);
      },
      scoreFormatter: (score) => `${Math.round(score)}/75`,
      percent: (score) => Math.round(((score - 15) / 60) * 100),
      sectionScoreFormatter: (score) => Math.round(score),
      sectionMeterPercent: (score) => (score / 25) * 100
    },
    'training-evaluation': {
      label: 'Evaluasi Pelatihan',
      countLabel: 'Survei Evaluasi | 16 Pertanyaan',
      intro: 'Isi survei evaluasi pelatihan AI berikut. Pertanyaan 1-10 memakai skala 1-5, pertanyaan 14 memakai skala 0-10, sisanya masukan terbuka.',
      sectionLabel: 'Bagian',
      scoreLabel: 'Skor Evaluasi Pelatihan',
      statusPrefix: 'Status Evaluasi',
      resultNoun: 'pelatihan',
      companyLabel: 'Perusahaan / Organisasi (opsional)',
      companyRequired: false,
      goalRequired: false,
      defaultTrainingGoal: 'Survei Evaluasi Pelatihan AI',
      showGoalField: false,
      followupTitle: 'Terima Kasih atas Evaluasi Anda',
      followupText: 'Masukan Anda membantu AIHebat meningkatkan kualitas pelatihan berikutnya.',
      scales: ['Q1-10: 1 = Sangat Kurang/Tidak Setuju', 'Q1-10: 5 = Sangat Baik/Sangat Setuju', 'Q14: 0 = Tidak mungkin merekomendasikan', 'Q14: 10 = Sangat mungkin merekomendasikan'],
      sections: trainingEvaluationSections,
      sectionScoreType: 'average',
      maxScore: 5,
      levelFromScore(score) {
        if (score < 2.2) return { label: 'Perlu Perbaikan Serius', className: 'level-low' };
        if (score < 3.0) return { label: 'Perlu Perbaikan', className: 'level-watch' };
        if (score < 3.8) return { label: 'Cukup Baik', className: 'level-mid' };
        if (score < 4.5) return { label: 'Baik', className: 'level-good' };
        return { label: 'Sangat Baik', className: 'level-strong' };
      },
      scoreFormatter: (score) => score.toFixed(1),
      percent: (score) => Math.round((score / 5) * 100),
      sectionScoreFormatter: (score) => score.toFixed(1),
      sectionMeterPercent: (score) => (score / 5) * 100
    }
  };

  let currentStep = 0;
  let latestAssessmentRecord = null;
  let activeMode = 'organization';
  let activeConfig = assessmentConfigs[activeMode];
  let pillars = activeConfig.sections;

  const getLevel = (score, scope = 'overall') => {
    if (scope === 'section' && activeConfig.sectionLevelFromScore) return activeConfig.sectionLevelFromScore(score);
    return activeConfig.levelFromScore(score);
  };

  const escapeHtml = (value) => value.replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;'
  }[char]));

  const getQuestionDefinition = (question) => {
    if (typeof question === 'string') {
      return {
        text: question,
        type: 'scale5',
        required: true
      };
    }

    return {
      type: 'scale5',
      required: true,
      ...question
    };
  };

  const getQuestionName = (pillar, questionIndex) => `${pillar.id}-${questionIndex + 1}`;

  const getQuestionValue = (pillar, question, questionIndex) => {
    const questionDef = getQuestionDefinition(question);
    const questionName = getQuestionName(pillar, questionIndex);

    if (questionDef.type === 'textarea') {
      const field = form.querySelector(`textarea[name="${questionName}"]`);
      return field ? field.value.trim() : '';
    }

    const selected = form.querySelector(`input[name="${questionName}"]:checked`);
    return selected ? selected.value : '';
  };

  const isQuestionAnswered = (pillar, question, questionIndex) => {
    const questionDef = getQuestionDefinition(question);
    if (questionDef.required === false) return true;
    return getQuestionValue(pillar, question, questionIndex) !== '';
  };

  const getQuestionScore = (questionDef, answerValue) => {
    if (answerValue === '') return null;

    if (questionDef.type === 'scale5') return Number(answerValue);
    if (questionDef.type === 'scale10') return 1 + ((Number(answerValue) / 10) * 4);

    if (questionDef.type === 'choice' && Array.isArray(questionDef.options)) {
      const selectedOption = questionDef.options.find((option) => {
        if (typeof option === 'string') return option === answerValue;
        return option && typeof option === 'object' && String(option.value) === String(answerValue);
      });

      if (selectedOption && typeof selectedOption === 'object' && Number.isFinite(selectedOption.score)) {
        return Number(selectedOption.score);
      }
    }

    return null;
  };

  const formatSectionScore = (score) => {
    if (typeof activeConfig.sectionScoreFormatter === 'function') return activeConfig.sectionScoreFormatter(score);
    if (activeConfig.sectionScoreType === 'sum') return Math.round(score);
    return Number(score.toFixed(1));
  };

  const getSectionMeterPercent = (score) => {
    if (typeof activeConfig.sectionMeterPercent === 'function') return activeConfig.sectionMeterPercent(score);
    if (activeConfig.sectionScoreType === 'sum') return (score / 25) * 100;
    return (score / 5) * 100;
  };

  const renderQuestions = () => {
    progressSteps.innerHTML = pillars.map((pillar, index) => `
      <button type="button" class="assessment-progress-step" data-step="${index}" aria-label="Buka ${pillar.title}">
        <span>${index + 1}</span>
        <strong>${pillar.title}</strong>
      </button>
    `).join('');

    questionList.innerHTML = pillars.map((pillar, pillarIndex) => `
      <section class="assessment-pillar" data-pillar="${pillar.id}" data-step="${pillarIndex}">
        <div class="assessment-pillar-heading">
          <i class="bi ${pillar.icon}"></i>
          <div>
            <span>${activeConfig.sectionLabel} ${pillarIndex + 1}</span>
            <h2>${pillar.title}</h2>
          </div>
        </div>
        ${pillar.questions.map((question, questionIndex) => {
          const questionDef = getQuestionDefinition(question);
          const name = getQuestionName(pillar, questionIndex);

          if (questionDef.type === 'textarea') {
            return `
              <fieldset class="assessment-question">
                <legend>${questionIndex + 1}. ${questionDef.text}</legend>
                <textarea name="${name}" class="form-control" rows="4" ${questionDef.required === false ? '' : 'required'}></textarea>
              </fieldset>
            `;
          }

          let optionsMarkup = '';
          if (questionDef.type === 'choice') {
            const options = Array.isArray(questionDef.options) ? questionDef.options : [];
            optionsMarkup = options.map((option, optionIndex) => {
              const optionValue = typeof option === 'string' ? option : option.value;
              const optionLabel = typeof option === 'string' ? option : option.label;
              return `
                <label>
                  <input type="radio" name="${name}" value="${escapeHtml(String(optionValue))}" ${questionDef.required && optionIndex === 0 ? 'required' : ''}>
                  <span>${escapeHtml(String(optionLabel))}</span>
                </label>
              `;
            }).join('');
          } else if (questionDef.type === 'scale10') {
            optionsMarkup = Array.from({ length: 11 }, (_, value) => `
              <label>
                <input type="radio" name="${name}" value="${value}" required>
                <span>${value}</span>
              </label>
            `).join('');
          } else {
            optionsMarkup = [1, 2, 3, 4, 5].map((value) => `
              <label>
                <input type="radio" name="${name}" value="${value}" required>
                <span>${value}</span>
              </label>
            `).join('');
          }

          return `
            <fieldset class="assessment-question">
              <legend>${questionIndex + 1}. ${questionDef.text}</legend>
              <div class="assessment-options">
                ${optionsMarkup}
              </div>
            </fieldset>
          `;
        }).join('')}
      </section>
    `).join('');

    progressSteps.querySelectorAll('.assessment-progress-step').forEach((button) => {
      button.addEventListener('click', () => {
        const nextStep = Number(button.dataset.step);
        if (!Number.isFinite(nextStep)) return;
        if (nextStep > currentStep && !isStepComplete(currentStep)) return;
        currentStep = nextStep;
        updateStep();
      });
    });
  };

  const applyAssessmentMode = (mode) => {
    activeMode = assessmentConfigs[mode] ? mode : 'organization';
    activeConfig = assessmentConfigs[activeMode];
    pillars = activeConfig.sections;
    currentStep = 0;
    latestAssessmentRecord = null;
    form.reset();
    result.hidden = true;
    leadError.hidden = true;
    saveStatus.hidden = true;
    saveStatus.textContent = '';

    if (modeChip) modeChip.textContent = activeConfig.label;
    if (countChip) countChip.textContent = activeConfig.countLabel;
    if (introText) introText.textContent = activeConfig.intro;
    if (companyLabel) companyLabel.textContent = activeConfig.companyLabel;
    if (companyInput) companyInput.required = activeConfig.companyRequired;
    if (goalInput) {
      goalInput.required = Boolean(activeConfig.goalRequired);
      if (!goalInput.required) goalInput.value = '';
    }
    if (goalField) goalField.hidden = activeConfig.showGoalField === false;
    if (followupTextElement) followupTextElement.textContent = activeConfig.followupText;
    const followupTitle = document.querySelector('.assessment-followup h3');
    if (followupTitle) followupTitle.textContent = activeConfig.followupTitle;
    scaleLabels.forEach((label, index) => {
      const scaleText = activeConfig.scales[index] || '';
      label.textContent = scaleText;
      label.hidden = scaleText === '';
    });
    modeButtons.forEach((button) => {
      const isActive = button.dataset.assessmentMode === activeMode;
      button.classList.toggle('active', isActive);
      button.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });

    renderQuestions();
    updateStep();
  };

  const isStepComplete = (step) => {
    const pillar = pillars[step];
    if (!pillar) return false;
    const missing = pillar.questions.findIndex((question, index) => {
      return !isQuestionAnswered(pillar, question, index);
    });
    if (missing === -1) return true;

    const questionName = getQuestionName(pillar, missing);
    const fieldset = form.querySelector(`[name="${questionName}"]`)?.closest('.assessment-question');
    if (fieldset) {
      fieldset.classList.add('assessment-question-warning');
      fieldset.scrollIntoView({ behavior: 'smooth', block: 'center' });
      setTimeout(() => fieldset.classList.remove('assessment-question-warning'), 1200);
    }
    return false;
  };

  const updateStep = () => {
    const onLeadStep = currentStep >= pillars.length;
    questionList.querySelectorAll('.assessment-pillar').forEach((section) => {
      section.classList.toggle('active', Number(section.dataset.step) === currentStep && !onLeadStep);
    });

    progressSteps.querySelectorAll('.assessment-progress-step').forEach((button) => {
      const step = Number(button.dataset.step);
      button.classList.toggle('active', step === currentStep && !onLeadStep);
      button.classList.toggle('complete', step < currentStep || isStepCompleteSilent(step));
    });

    leadForm.hidden = !onLeadStep;
    progressLabel.textContent = onLeadStep ? 'Data peserta' : `${activeConfig.sectionLabel} ${currentStep + 1} dari ${pillars.length}`;
    prevButton.hidden = currentStep === 0;
    nextButton.hidden = onLeadStep;
    submitButton.hidden = !onLeadStep;
    result.hidden = true;
  };

  const isStepCompleteSilent = (step) => {
    const pillar = pillars[step];
    if (!pillar) return false;
    return pillar.questions.every((question, index) => isQuestionAnswered(pillar, question, index));
  };

  const getPillarAnswers = (pillar) => {
    return pillar.questions.map((question, index) => {
      const questionDef = getQuestionDefinition(question);
      const answerValue = getQuestionValue(pillar, question, index);
      return {
        question: questionDef.text,
        answer: answerValue,
        score: getQuestionScore(questionDef, answerValue)
      };
    });
  };

  const getPillarScore = (pillar) => {
    const values = getPillarAnswers(pillar)
      .map((answer) => answer.score)
      .filter((value) => Number.isFinite(value));
    if (!values.length) return 0;
    const total = values.reduce((sum, value) => sum + value, 0);
    return activeConfig.sectionScoreType === 'sum' ? total : total / values.length;
  };

  const calculateAssessment = () => {
    const scores = pillars.map((pillar) => {
      const score = getPillarScore(pillar);
      const level = getLevel(score, 'section');
      return { ...pillar, score, level, answers: getPillarAnswers(pillar) };
    });

    const average = activeConfig.sectionScoreType === 'sum'
      ? scores.reduce((sum, item) => sum + item.score, 0)
      : scores.reduce((sum, item) => sum + item.score, 0) / scores.length;
    const averageLevel = getLevel(average);
    const weakest = scores.reduce((lowest, item) => item.score < lowest.score ? item : lowest, scores[0]);
    return { scores, average, averageLevel, weakest };
  };

  const buildAssessmentRecord = (assessment) => {
    const participantName = nameInput.value.trim();
    const companyName = companyInput.value.trim();
    const weakestScore = formatSectionScore(assessment.weakest.score);
    const priorityRecommendation = `Fokus pada ${assessment.weakest.title}, karena area ini memiliki skor terendah (${weakestScore}). ${assessment.weakest.recommendation}`;
    const readinessValue = Math.max(0, Math.min(100, activeConfig.percent(assessment.average)));
    return {
      id: `assessment-${Date.now()}`,
      createdAt: new Date().toISOString(),
      type: activeMode,
      typeLabel: activeConfig.label,
      name: participantName,
      company: companyName,
      email: emailInput.value.trim(),
      trainingGoal: goalInput ? (goalInput.value.trim() || activeConfig.defaultTrainingGoal || '') : (activeConfig.defaultTrainingGoal || ''),
      overallScore: activeConfig.scoreFormatter(assessment.average),
      readinessPercent: readinessValue,
      overallLevel: assessment.averageLevel.label,
      weakestPillar: assessment.weakest.title,
      recommendation: priorityRecommendation,
      pillarScores: assessment.scores.map((item) => ({
        pillar: item.title,
        score: formatSectionScore(item.score),
        level: item.level.label
      })),
      answers: assessment.scores.map((item) => ({
        pillar: item.title,
        answers: item.answers
      }))
    };
  };

  const saveAssessmentLocally = (record, reason = '') => {
    const storageKey = 'aihebat-assessment-submissions';
    const existing = JSON.parse(localStorage.getItem(storageKey) || '[]');
    existing.unshift({
      ...record,
      storage: 'local',
      fallbackReason: reason
    });
    localStorage.setItem(storageKey, JSON.stringify(existing.slice(0, 100)));
  };

  window.getAIHebatAssessmentSubmissions = () => {
    try {
      return JSON.parse(localStorage.getItem('aihebat-assessment-submissions') || '[]');
    } catch (error) {
      return [];
    }
  };

  const submitAssessment = async (record) => {
    const payload = new URLSearchParams();
    payload.set('name', record.name);
    payload.set('company', record.company);
    payload.set('email', record.email);
    payload.set('assessment_mode', record.type);
    payload.set('assessment_type', record.typeLabel);
    payload.set('training_goal', record.trainingGoal);
    payload.set('overall_score', record.overallScore);
    payload.set('overall_level', record.overallLevel);
    payload.set('weakest_pillar', record.weakestPillar);
    payload.set('recommendation', record.recommendation);
    payload.set('pillar_scores', JSON.stringify(record.pillarScores));
    payload.set('answers', JSON.stringify(record.answers));

    if (window.location.protocol === 'file:') {
      throw new Error('local-file');
    }

    const response = await fetch('forms/assessment.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
      },
      body: payload.toString()
    });

    if (!response.ok) {
      const message = await response.text();
      throw new Error(message || `HTTP ${response.status}`);
    }
  };

  const renderResults = (assessment) => {
    const readinessValue = Math.max(0, Math.min(100, activeConfig.percent(assessment.average)));
    if (resultKicker) resultKicker.textContent = activeConfig.scoreLabel;
    overallScore.textContent = activeConfig.scoreFormatter(assessment.average);
    overallLevel.textContent = assessment.averageLevel.label;
    overallLevel.className = `assessment-level ${assessment.averageLevel.className}`;
    overallStatus.textContent = `${activeConfig.statusPrefix}: ${assessment.averageLevel.label}`;
    readinessPercent.textContent = `${readinessValue}%`;
    readinessChart.style.setProperty('--ready-percent', `${readinessValue}%`);

    pillarResults.innerHTML = assessment.scores.map((item) => `
      <article class="assessment-result-card">
        <div class="assessment-result-topline">
          <h3>${item.title}</h3>
          <strong>${formatSectionScore(item.score)}</strong>
        </div>
        <div class="assessment-meter" aria-hidden="true">
          <span style="width: ${getSectionMeterPercent(item.score)}%"></span>
        </div>
        <p class="assessment-level ${item.level.className}">${item.level.label}</p>
        <p>${item.level.description ? item.level.description + ' ' : ''}${item.recommendation}</p>
      </article>
    `).join('');

    const participantName = escapeHtml(nameInput.value.trim());
    const companyName = escapeHtml(companyInput.value.trim() || '-');
    const weakestScoreText = formatSectionScore(assessment.weakest.score);
    const goalText = goalInput && goalInput.value ? `<br><strong>Tujuan pelatihan:</strong> ${escapeHtml(goalInput.value)}` : '';
    const targetText = activeMode === 'organization' ? companyName : participantName;
    recommendation.innerHTML = `<strong>Prioritas awal untuk ${targetText}:</strong> fokus pada ${assessment.weakest.title}, karena area ini memiliki skor terendah (${weakestScoreText}). ${assessment.weakest.recommendation}<br><strong>Peserta:</strong> ${participantName}${goalText}`;

    const followupText = [
      `Halo AIHebat, saya ingin konsultasi tindak lanjut hasil AI Readiness Assessment ${activeConfig.label}.`,
      `Nama: ${nameInput.value.trim()}`,
      `Perusahaan/Organisasi: ${companyInput.value.trim() || '-'}`,
      `Email: ${emailInput.value.trim()}`,
      goalInput && goalInput.value ? `Tujuan pelatihan: ${goalInput.value}` : '',
      `Skor: ${activeConfig.scoreFormatter(assessment.average)} (${assessment.averageLevel.label})`,
      `Prioritas: ${assessment.weakest.title} (${weakestScoreText})`,
      `Mohon dibantu arahan untuk meningkatkan maturity AI ${activeConfig.resultNoun}.`
    ].filter(Boolean).join('\n');

    whatsappLink.href = `https://wa.me/6287825785182?text=${encodeURIComponent(followupText)}`;
    emailLink.href = `mailto:admin@aihebat.com?subject=${encodeURIComponent('Tindak Lanjut AI Readiness Assessment ' + activeConfig.label + ' - ' + (companyInput.value.trim() || nameInput.value.trim()))}&body=${encodeURIComponent(followupText)}`;

    result.hidden = false;
    result.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  prevButton.addEventListener('click', () => {
    currentStep = Math.max(0, currentStep - 1);
    updateStep();
  });

  nextButton.addEventListener('click', () => {
    if (!isStepComplete(currentStep)) return;
    currentStep = Math.min(pillars.length, currentStep + 1);
    updateStep();
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  questionList.addEventListener('change', () => {
    updateStep();
  });

  questionList.addEventListener('input', () => {
    updateStep();
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!pillars.every((_, index) => isStepCompleteSilent(index))) return;
    const emailValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.value.trim());
    const goalValue = goalInput ? goalInput.value.trim() : '';
    const goalRequired = Boolean(activeConfig.goalRequired);
    if (!nameInput.value.trim() || (activeConfig.companyRequired && !companyInput.value.trim()) || !emailValid || (goalRequired && !goalValue)) {
      leadError.hidden = false;
      if (activeConfig.companyRequired && goalRequired) {
        leadError.textContent = 'Lengkapi nama, email valid, tujuan pelatihan, dan perusahaan/organisasi.';
      } else if (goalRequired) {
        leadError.textContent = 'Lengkapi nama, email valid, dan tujuan pelatihan.';
      } else if (activeConfig.companyRequired) {
        leadError.textContent = 'Lengkapi nama, email valid, dan perusahaan/organisasi.';
      } else {
        leadError.textContent = 'Lengkapi nama dan email valid.';
      }
      leadForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }
    leadError.hidden = true;
    saveStatus.hidden = false;
    saveStatus.className = 'assessment-save-status';
    saveStatus.textContent = 'Menyimpan data assessment...';
    submitButton.disabled = true;

    try {
      const assessment = calculateAssessment();
      const record = buildAssessmentRecord(assessment);
      latestAssessmentRecord = record;
      await submitAssessment(record);
      saveStatus.className = 'assessment-save-status success';
      saveStatus.textContent = 'Data assessment berhasil tersimpan di server.';
      renderResults(assessment);
    } catch (error) {
      try {
        const assessment = calculateAssessment();
        const record = buildAssessmentRecord(assessment);
        latestAssessmentRecord = record;
        const reason = error instanceof Error && error.message ? error.message : 'server-unavailable';
        saveAssessmentLocally(record, reason);
        saveStatus.className = 'assessment-save-status local';
        saveStatus.textContent = 'Server belum tersedia. Data assessment tersimpan lokal di browser ini.';
        renderResults(assessment);
      } catch (localError) {
        saveStatus.className = 'assessment-save-status error';
        saveStatus.textContent = 'Data assessment belum tersimpan. Browser menolak penyimpanan lokal.';
      }
    } finally {
      submitButton.disabled = false;
    }
  });

  if (resetButton) {
    resetButton.addEventListener('click', () => {
      form.reset();
      currentStep = 0;
      leadError.hidden = true;
      saveStatus.hidden = true;
      saveStatus.textContent = '';
      latestAssessmentRecord = null;
      result.hidden = true;
      updateStep();
      questionList.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }

  modeButtons.forEach((button) => {
    button.addEventListener('click', () => applyAssessmentMode(button.dataset.assessmentMode));
  });

  applyAssessmentMode(activeMode);

  pdfButton.addEventListener('click', () => {
    if (!latestAssessmentRecord) return;

    const reportWindow = window.open('', '_blank');
    if (!reportWindow) return;

    const readinessValue = Number.isFinite(latestAssessmentRecord.readinessPercent) ? latestAssessmentRecord.readinessPercent : 0;
    const pillarRows = latestAssessmentRecord.pillarScores.map((item) => `
      <tr>
        <td>${escapeHtml(item.pillar)}</td>
        <td>${escapeHtml(String(item.score))}</td>
        <td>${escapeHtml(item.level)}</td>
      </tr>
    `).join('');

    const answerRows = latestAssessmentRecord.answers.map((pillar) => `
      <h3>${escapeHtml(pillar.pillar)}</h3>
      <ol>
        ${pillar.answers.map((answer) => `<li>${escapeHtml(answer.question)} <strong>Jawaban:</strong> ${escapeHtml(String(answer.answer ?? '-'))}${Number.isFinite(answer.score) ? ` <strong>(Skor: ${Number(answer.score).toFixed(1)})</strong>` : ''}</li>`).join('')}
      </ol>
    `).join('');

    const sectionLabel = latestAssessmentRecord.type === 'personal'
      ? 'Area'
      : (latestAssessmentRecord.type === 'training-evaluation' ? 'Bagian' : 'Pilar');

    reportWindow.document.write(`
      <!doctype html>
      <html>
      <head>
        <meta charset="utf-8">
        <title>AI Readiness Assessment ${escapeHtml(latestAssessmentRecord.typeLabel || '')} - ${escapeHtml(latestAssessmentRecord.company)}</title>
        <style>
          @page { margin: 18mm; }
          body { color: #24324a; font-family: Arial, sans-serif; line-height: 1.5; margin: 0; }
          .header { align-items: center; border-bottom: 2px solid #47b2e4; display: flex; justify-content: space-between; padding-bottom: 14px; }
          .brand { align-items: center; display: flex; gap: 12px; }
          .brand img { height: 42px; width: auto; }
          .brand strong { color: #37517e; display: block; font-size: 18px; }
          .brand span, .meta { color: #5d6b82; font-size: 12px; }
          h1 { color: #37517e; font-size: 28px; margin: 22px 0 8px; }
          h2 { color: #37517e; font-size: 19px; margin: 24px 0 10px; }
          h3 { color: #37517e; font-size: 15px; margin: 16px 0 6px; }
          .summary { background: #f3f9fe; border: 1px solid #d9ebff; border-radius: 10px; display: grid; grid-template-columns: 1fr 160px; gap: 18px; padding: 16px; }
          .score { color: #47b2e4; font-size: 48px; font-weight: 800; line-height: 1; margin: 0; }
          .level { color: #37517e; font-weight: 800; margin: 8px 0 0; }
          .chart { background: conic-gradient(#47b2e4 0 ${readinessValue}%, #d9ebff ${readinessValue}% 100%); border-radius: 50%; display: grid; height: 150px; place-items: center; width: 150px; }
          .chart div { align-items: center; background: #fff; border-radius: 50%; color: #37517e; display: flex; flex-direction: column; font-weight: 800; height: 96px; justify-content: center; width: 96px; }
          .chart strong { font-size: 28px; }
          table { border-collapse: collapse; width: 100%; }
          th, td { border: 1px solid #d9e5f2; padding: 9px; text-align: left; vertical-align: top; }
          th { background: #eef6fc; color: #37517e; }
          .callout { background: #fff7df; border: 1px solid #f0dc9a; border-radius: 10px; margin-top: 14px; padding: 12px; }
          .cta { background: #37517e; border-radius: 10px; color: #fff; margin-top: 18px; padding: 14px; }
          .cta a { color: #fff; font-weight: 800; }
          ol { margin-top: 6px; padding-left: 22px; }
          li { margin-bottom: 5px; }
          .footer { border-top: 1px solid #d9e5f2; color: #5d6b82; font-size: 12px; margin-top: 24px; padding-top: 10px; }
          @media print { .no-print { display: none; } body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
        </style>
      </head>
      <body>
        <header class="header">
          <div class="brand">
            <img src="${new URL('assets/img/logo.webp', window.location.href).href}" alt="AIHebat.com">
            <div>
              <strong>AIHebat.com</strong>
              <span>AI Readiness Assessment ${escapeHtml(latestAssessmentRecord.typeLabel || '')}</span>
            </div>
          </div>
          <div class="meta">${new Date(latestAssessmentRecord.createdAt).toLocaleString('id-ID')}</div>
        </header>

        <h1>Hasil AI Readiness Assessment ${escapeHtml(latestAssessmentRecord.typeLabel || '')}</h1>
        <p><strong>Peserta:</strong> ${escapeHtml(latestAssessmentRecord.name)}<br>
        <strong>Perusahaan:</strong> ${escapeHtml(latestAssessmentRecord.company)}<br>
        <strong>Email:</strong> ${escapeHtml(latestAssessmentRecord.email)}${latestAssessmentRecord.trainingGoal ? '<br><strong>Tujuan pelatihan:</strong> ' + escapeHtml(latestAssessmentRecord.trainingGoal) : ''}</p>

        <section class="summary">
          <div>
            <p class="score">${escapeHtml(latestAssessmentRecord.overallScore)}</p>
            <p class="level">Status: ${escapeHtml(latestAssessmentRecord.overallLevel)}</p>
            <p>Kesiapan ${escapeHtml(latestAssessmentRecord.type === 'personal' ? 'pribadi' : 'organisasi')}: <strong>${readinessValue}%</strong></p>
          </div>
          <div class="chart"><div><strong>${readinessValue}%</strong><span>Kesiapan</span></div></div>
        </section>

        <h2>Skor per ${escapeHtml(sectionLabel)}</h2>
        <table>
          <thead><tr><th>Pilar</th><th>Skor</th><th>Level</th></tr></thead>
          <tbody>${pillarRows}</tbody>
        </table>

        <div class="callout"><strong>Prioritas tindak lanjut:</strong> ${escapeHtml(latestAssessmentRecord.recommendation)}</div>

        <section class="cta">
          <strong>CTA Tindak Lanjut</strong>
          <p>Diskusikan hasil ini dengan AIHebat untuk menyusun roadmap peningkatan maturity AI ${escapeHtml(latestAssessmentRecord.type === 'personal' ? 'pribadi' : 'organisasi')}.</p>
          <p>WhatsApp: <a href="https://wa.me/6287825785182">+62 878 2578 5182</a><br>Email: <a href="mailto:admin@aihebat.com">admin@aihebat.com</a></p>
        </section>

        <h2>Detail Jawaban</h2>
        ${answerRows}

        <p class="footer">Dokumen ini dibuat otomatis oleh AIHebat.com berdasarkan jawaban peserta assessment.</p>
        <button class="no-print" onclick="window.print()">Download / Save as PDF</button>
        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));<\/script>
      </body>
      </html>
    `);
    reportWindow.document.close();
  });
}

/**
 * Assessment related items on detail pages
 */
function initAssessmentRelated() {
  if (!document.body.classList.contains('assessment-details-page')) return;

  const relatedList = document.querySelector('.assessment-related-list');
  const currentId = document.body.getAttribute('data-assessment-id');

  if (!relatedList || !currentId) return;

  fetch('assets/data/assessments.json', { cache: 'no-store' })
    .then((response) => response.ok ? response.json() : [])
    .then((items) => {
      if (!Array.isArray(items) || !items.length) return;

      const current = items.find((item) => item.id === currentId);
      if (!current) return;

      const related = items
        .filter((item) => item.id !== currentId)
        .sort((a, b) => {
          const aScore = (a.category === current.category ? 2 : 0) + (a.status === current.status ? 1 : 0);
          const bScore = (b.category === current.category ? 2 : 0) + (b.status === current.status ? 1 : 0);
          return bScore - aScore;
        })
        .slice(0, 3);

      if (!related.length) return;

      relatedList.innerHTML = related
        .map((item) => `<li><a href="${item.detail_url}">${item.title}</a></li>`)
        .join('');
    })
    .catch(() => {
      // keep static/fallback list if data file is not available
    });
}

/**
 * Assessment detail share buttons
 */
function initAssessmentShare() {
  if (!document.body.classList.contains('assessment-details-page')) return;

  const buttons = Array.from(document.querySelectorAll('.assessment-share-btn'));
  if (!buttons.length) return;

  const currentUrl = window.location.href;
  const shareTitle = document.querySelector('.assessment-article .title')?.textContent?.trim() || document.title;

  const encodedUrl = encodeURIComponent(currentUrl);
  const encodedText = encodeURIComponent(shareTitle);

  buttons.forEach((button) => {
    button.addEventListener('click', async () => {
      const platform = button.getAttribute('data-platform');

      if (platform === 'copy') {
        try {
          await navigator.clipboard.writeText(currentUrl);
          const prev = button.textContent;
          button.textContent = 'Tersalin';
          setTimeout(() => {
            button.textContent = prev;
          }, 1200);
        } catch (error) {
          button.textContent = 'Gagal';
        }
        return;
      }

      let shareUrl = '';
      if (platform === 'whatsapp') {
        shareUrl = `https://wa.me/?text=${encodedText}%20${encodedUrl}`;
      }
      if (platform === 'linkedin') {
        shareUrl = `https://www.linkedin.com/sharing/share-offsite/?url=${encodedUrl}`;
      }
      if (platform === 'x') {
        shareUrl = `https://x.com/intent/tweet?text=${encodedText}&url=${encodedUrl}`;
      }
      if (platform === 'facebook') {
        shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}`;
      }

      if (shareUrl) {
        window.open(shareUrl, '_blank', 'noopener,noreferrer');
      }
    });
  });
}

/**
 * Homepage training flyer popup
 */
function initTrainingFlyerPopup() {
  if (!document.body.classList.contains('index-page')) return;

  const popup = document.getElementById('training-flyer-popup');
  if (!popup) return;

  const closeButtons = Array.from(popup.querySelectorAll('[data-flyer-close]'));
  const closeButton = popup.querySelector('.training-flyer-close');

  const openPopup = () => {
    popup.hidden = false;
    document.body.classList.add('flyer-popup-open');
    setTimeout(() => closeButton?.focus({ preventScroll: true }), 50);
  };

  const closePopup = () => {
    popup.hidden = true;
    document.body.classList.remove('flyer-popup-open');
  };

  closeButtons.forEach((button) => {
    button.addEventListener('click', closePopup);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !popup.hidden) {
      closePopup();
    }
  });

  setTimeout(openPopup, 700);
}

window.addEventListener('load', initThemeToggle);
window.addEventListener('load', initBlogInteractivity);
window.addEventListener('load', initAssessmentInteractivity);
window.addEventListener('load', initAssessmentRelated);
window.addEventListener('load', initAssessmentShare);
window.addEventListener('load', initTrainingFlyerPopup);

})();
