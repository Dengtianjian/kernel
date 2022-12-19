export function use(name, componentClass) {
  window.customElements.define(name, componentClass);
}

class CCustomElement extends HTMLElement {
  constructor() {
    super();
    const template = document.querySelector("#addonItem");
    const templateContent = template.content;
    this.addProp(".addon-icon", "icon", "src");
    this.addProp(".addon-name", "name", "innerText");
    const el = templateContent.cloneNode(true);
    this.el = el;
    this.attachShadow({ mode: "open" }).appendChild(el);
  }
}

export class AddonItem extends CCustomElement {
  props = {
    icon: {},
    name: "",
    id: "",
    created_at: "",
    version: "",
    identifier: "",
  };
  el = null;
  constructor() {
    super();
    const template = document.querySelector("#addonItem");
    const templateContent = template.content;
    this.addProp(".addon-icon", "icon", "src");
    this.addProp(".addon-name", "name", "innerText");
    const el = templateContent.cloneNode(true);
    this.el = el;
    this.attachShadow({ mode: "open" }).appendChild(el);
  }
  connectedCallback() {}
  static get observedAttributes() {
    return ["icon", "name", "id", "created_at", "version", "identifier"];
  }
  attributeChangedCallback(name, oldValue, newValue) {
    if (name in this.props) {
      this.props[name]["value"] = newValue;
    }
    this.updateRender();
  }
  updateProp(selector, attributeName, value = "") {
    this.shadowRoot.querySelector(selector)[attributeName] = value;
  }
  updateRender() {
    const props = this.props;
    for (const key in props) {
      const prop = props[key];
      if (prop) {
        this.updateProp(prop["selector"], prop["attributeName"], prop["value"]);
      }
    }
  }
  addProp(selector, keyName, attributeName, value = null) {
    this.props[keyName] = {
      selector,
      attributeName,
      value,
    };
  }
}
export default {
  use,
  AddonItem,
};

// <script type="module" defer>
//   import {
//     use,
//     AddonItem,
//   } from "/source/plugin/gstudio_devaddon/Views/js/components.js";
//   use("addon-item", AddonItem);
// </script>
