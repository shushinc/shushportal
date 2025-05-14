(function () {
  'use strict';
  setInterval(() => {
    document.querySelectorAll('[data-testid="dashcard"]').forEach(container => {
      const titleElement = container.querySelector('div[data-testid="scalar-title"]>h3>div');
      const valueElement = container.querySelector('div[data-testid="scalar-container"]>span>h1');
      if (!titleElement || !valueElement) {
        return;
      }
      const text = titleElement.innerText.trim();
      const value = valueElement.innerText.trim();
      if (text.includes('-o-')) {
        container.parentNode.classList.add("container-small");
        if (value.includes('-') && !container.classList.contains("decrease")) {
          container.classList.add("decrease");
          const heading = container.querySelector('h1.ScalarValue');
          heading.textContent = heading.textContent.replace('-', '');
        }
        else if (value.includes('+') && !container.classList.contains("increase")) {
          container.classList.add("increase");
          const heading = container.querySelector('h1.ScalarValue');
          heading.textContent = heading.textContent.replace('+', '');
        }
        else if (!container.classList.contains("decrease") && !container.classList.contains("increase") && !container.classList.contains("equals")) {
          container.classList.add("equals");
        }
      }
      else {
        if (!container.classList.contains("dashcard")) {
          container.classList.add("dashcard");
        }
      }
    }
    );
  }, 3000);
}
)();
