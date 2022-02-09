const scriptEl = document.createElement("script");
scriptEl.src = "source/plugin/kernel/Assets/js/tinymce/tinymce.min.js";
document.body.appendChild(scriptEl);
const Tinymce = {
  defaultOptions: {
    language: "zh_CN",
    suffix: ".min",
    branding: false,
  },
  options: [],
  init(options) {
    this.options.push(Object.assign({}, this.defaultOptions, options));
  },
  _init() {
    this.options.forEach((option) => {
      tinymce.init(option).then((res) => {
        if (option.onload) {
          option.onload(res[0]);
        }
      });
    });
  },
};
scriptEl.onload = function () {
  Tinymce._init();
};
