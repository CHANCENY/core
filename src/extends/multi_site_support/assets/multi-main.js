(function (){

    const sitesContainer = document.getElementById('sitesContainer');
    const addSiteBtn = document.getElementById('addSiteBtn');
    const previewBox = document.getElementById('previewBox');
    const previewContent = document.getElementById('previewContent');

    const sites = JSON.parse(document.querySelector('noscript').textContent);
    const themes = JSON.parse(document.querySelector('noscript#themes').textContent);
    const primaryRoles = JSON.parse(document.querySelector('noscript#primary').textContent);
    const additionalRoles = JSON.parse(document.querySelector('noscript#secondary').textContent);

    let siteCount = 0;

// Function to create a site block
    function createSiteBlock(site = {}) {
        siteCount++;
        const siteBlock = document.createElement('div');
        siteBlock.className = 'site-block';
        siteBlock.dataset.site = siteCount;

        siteBlock.innerHTML = `
    <h3>Site ${siteCount}</h3>
    <div class="form-group">
      <label for="siteDomain${siteCount}">Site Domain</label>
      <input type="text" id="siteDomain${siteCount}" name="siteDomain" value="${site.domain}" placeholder="example.com" required>
    </div>

    <div class="form-group">
      <label>Primary Role</label>
      <div class="roles">
        ${primaryRoles.map(role => `
          <label><input type="radio" ${site.primaryRole === role ? 'checked' : ''} name="primaryRole${siteCount}" value="${role}" required> ${role}</label>
        `).join('')}
      </div>
    </div>

    <div class="form-group">
      <label>Additional Roles</label>
      <div class="roles">
        ${additionalRoles.map(role => `
          <label><input type="checkbox" ${site.additionalRoles.includes(role) ? 'checked' : ''} name="additionalRoles${siteCount}" value="${role}"> ${role}</label>
        `).join('')}
      </div>
    </div>

    <div class="form-group">
      <label for="theme${siteCount}">Select Theme</label>
      <select id="theme${siteCount}" name="theme">
        ${themes.map(theme => `
        <option value="${theme.id}" ${site.theme === theme.id ? 'selected' : ''}>${theme.name}</option>
        `).join('')}
      </select>
    </div>

    <button type="button" data-id="${site?.id}" class="remove-site">Remove Site</button>
  `;

        // Add remove functionality
        siteBlock.querySelector('.remove-site').addEventListener('click', () => {
            const sendRemove = async (id)=>{
                const response = await fetch('/admin/multi-site-support/'+id+'/delete');
                const data = await response.json();
                console.log(data);
            }
            sendRemove(site?.id);
            sitesContainer.removeChild(siteBlock);
        });

        sitesContainer.appendChild(siteBlock);
    }

// Add initial site

    if (sites.length > 0) {
        sites.forEach(site => {
            createSiteBlock(site);
        });
    }
    else {
        createSiteBlock({
            domain: 'example.com',
            primaryRole: 'Administrator',
            additionalRoles: [],
            theme: 'default'
        });
    }

// Add new site on button click
    addSiteBtn.addEventListener('click', (e)=>createSiteBlock({
        domain: '',
        primaryRole: 'Administrator',
        additionalRoles: [],
        theme: 'default'
    }));

// Handle form submission
    document.getElementById('multisiteForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const siteBlocks = document.querySelectorAll('.site-block');
        const configData = [];

        siteBlocks.forEach(block => {
            const domain = block.querySelector('input[name="siteDomain"]').value;
            const primaryRole = block.querySelector(`input[name^="primaryRole"]:checked`)?.value || 'None';
            const additionalRolesSelected = Array.from(block.querySelectorAll(`input[name^="additionalRoles"]:checked`)).map(r => r.value);
            const theme = block.querySelector('select[name="theme"]').value;

            configData.push({
                domain,
                primaryRole,
                additionalRoles: additionalRolesSelected,
                theme
            });
        });

        // Render preview
        previewContent.innerHTML = '';
        configData.forEach((site, index) => {
            previewContent.innerHTML += `
      <p><strong>Site ${index + 1}:</strong></p>
      <ul>
        <li>Domain: ${site.domain}</li>
        <li>Primary Role: ${site.primaryRole}</li>
        <li>Additional Roles: ${site.additionalRoles.length ? site.additionalRoles.join(', ') : 'None'}</li>
        <li>Theme: ${site.theme}</li>
      </ul>
    `;
        });

        previewBox.style.display = 'block';

        const send = async ()=>{
            const response = await fetch('/admin/multi-site-support/save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(configData)
            });
            const data = await response.json();
            console.log(data);
        };
        send();
    });


})();