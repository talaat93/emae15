document.addEventListener('DOMContentLoaded', () => {
  const builder = document.querySelector('[data-services-builder]');
  if (!builder) return;

  const blocksHost = builder.querySelector('[data-blocks-host]');
  const blockTemplate = document.getElementById('services-block-template');
  const cardTemplate = document.getElementById('services-card-item-template');
  const faqTemplate = document.getElementById('services-faq-item-template');

  const typeLabels = {
    text: 'Texte',
    image_text: 'Texte + image',
    cards: 'Cartes services',
    faq: 'FAQ',
    cta: 'CTA final',
    html: 'HTML libre'
  };

  const uid = (prefix = 'blk_') => `${prefix}${Math.random().toString(36).slice(2, 10)}`;

  function updateTypePanels(block) {
    const typeSelect = block.querySelector('[data-block-type]');
    if (!typeSelect) return;

    if (typeSelect.dataset.defaultType && !typeSelect.dataset.initialized) {
      typeSelect.value = typeSelect.dataset.defaultType;
      typeSelect.dataset.initialized = '1';
    }

    const type = typeSelect.value;
    const labelNode = block.querySelector('[data-block-type-label]');
    if (labelNode) {
      labelNode.textContent = typeLabels[type] || type;
    }

    block.querySelectorAll('[data-type-panel]').forEach((panel) => {
      panel.hidden = panel.getAttribute('data-type-panel') !== type;
    });
  }

  function renumberItems(block) {
    [...block.querySelectorAll('[data-items]')].forEach((host) => {
      const kind = host.getAttribute('data-items');
      [...host.querySelectorAll('[data-item]')].forEach((item, itemIndex) => {
        item.querySelectorAll('[name]').forEach((field) => {
          field.name = field.name
            .replace(/blocks\[\d+]/, `blocks[${block.dataset.blockIndex || '0'}]`)
            .replace(/\[items]\[\d+]/, `[items][${itemIndex}]`);
          if (field.type === 'file') {
            if (kind === 'cards') {
              field.name = `block_card_image_${block.dataset.blockIndex || '0'}_${itemIndex}`;
            }
          }
        });
      });
    });
  }

  function renumberBlocks() {
    [...blocksHost.querySelectorAll('[data-block]')].forEach((block, index) => {
      block.dataset.blockIndex = String(index);

      const numberNode = block.querySelector('[data-block-number]');
      if (numberNode) {
        numberNode.textContent = String(index + 1);
      }

      block.querySelectorAll('[name]').forEach((field) => {
        field.name = field.name.replace(/blocks\[\d+]/, `blocks[${index}]`);
      });

      const imageField = block.querySelector('input[type="file"][name^="block_image_"]');
      if (imageField) {
        imageField.name = `block_image_${index}`;
      }

      renumberItems(block);
    });
  }

  function addItemToBlock(block, kind) {
    const itemsHost = block.querySelector(`[data-items="${kind}"]`);
    if (!itemsHost) return;

    const blockIndex = block.dataset.blockIndex || '0';
    const itemIndex = itemsHost.querySelectorAll('[data-item]').length;

    let html = '';
    if (kind === 'cards' && cardTemplate) {
      html = cardTemplate.innerHTML
        .replaceAll('__BLOCK_INDEX__', blockIndex)
        .replaceAll('__ITEM_INDEX__', String(itemIndex));
    }

    if (kind === 'faq' && faqTemplate) {
      html = faqTemplate.innerHTML
        .replaceAll('__BLOCK_INDEX__', blockIndex)
        .replaceAll('__ITEM_INDEX__', String(itemIndex));
    }

    if (!html.trim()) return;

    const wrapper = document.createElement('div');
    wrapper.innerHTML = html.trim();
    const node = wrapper.firstElementChild;
    if (node) {
      itemsHost.appendChild(node);
      renumberItems(block);
    }
  }

  function moveBlock(block, direction) {
    if (direction === 'up' && block.previousElementSibling) {
      blocksHost.insertBefore(block, block.previousElementSibling);
      renumberBlocks();
    }

    if (direction === 'down' && block.nextElementSibling) {
      blocksHost.insertBefore(block.nextElementSibling, block);
      renumberBlocks();
    }
  }

  function bindBlock(block) {
    updateTypePanels(block);

    const typeSelect = block.querySelector('[data-block-type]');
    if (typeSelect) {
      typeSelect.addEventListener('change', () => updateTypePanels(block));
    }

    block.querySelectorAll('[data-move]').forEach((button) => {
      button.addEventListener('click', () => moveBlock(block, button.dataset.move));
    });

    const removeBlockButton = block.querySelector('[data-remove-block]');
    if (removeBlockButton) {
      removeBlockButton.addEventListener('click', () => {
        const count = blocksHost.querySelectorAll('[data-block]').length;
        if (count <= 1) {
          alert('La page services doit garder au moins un bloc.');
          return;
        }
        block.remove();
        renumberBlocks();
      });
    }

    block.querySelectorAll('[data-add-item]').forEach((button) => {
      button.addEventListener('click', () => addItemToBlock(block, button.dataset.addItem));
    });

    block.addEventListener('click', (event) => {
      const removeItemButton = event.target.closest('[data-remove-item]');
      if (!removeItemButton) return;

      const item = removeItemButton.closest('[data-item]');
      if (item) {
        item.remove();
        renumberItems(block);
      }
    });
  }

  builder.querySelectorAll('[data-add-block]').forEach((button) => {
    button.addEventListener('click', () => {
      const type = button.dataset.addBlock;
      if (!blockTemplate) return;

      const html = blockTemplate.innerHTML
        .replaceAll('__BLOCK_ID__', uid())
        .replaceAll('__TYPE__', type)
        .replaceAll('__TYPE_LABEL__', typeLabels[type] || type);

      const wrapper = document.createElement('div');
      wrapper.innerHTML = html.trim();
      const block = wrapper.firstElementChild;

      if (!block) return;

      blocksHost.appendChild(block);
      bindBlock(block);
      renumberBlocks();
      block.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
  });

  blocksHost.querySelectorAll('[data-block]').forEach(bindBlock);
  renumberBlocks();
});
