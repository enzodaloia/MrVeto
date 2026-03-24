function modalShowForm(role) {
    document.getElementById('modal-step-1').style.display = 'none';
    document.getElementById('modal-step-2').style.display = 'block';
    document.getElementById('modal-selected-role').value = role;

    const titles = {
        'ROLE_VETO':  'Créer un compte vétérinaire',
        'ROLE_ADMIN': 'Créer un compte administrateur',
        'ROLE_USER':  'Créer un compte utilisateur'
    };
    document.getElementById('modal-title').textContent = titles[role] || 'Créer un compte';
    document.getElementById('modal-fields-veto').style.display = role === 'ROLE_VETO' ? 'block' : 'none';

    const rolesSelect = document.getElementById('modal-roles-select');
    Array.from(rolesSelect.options).forEach(opt => {
        opt.selected = opt.value === role;
    });
}

function modalShowStep1() {
    document.getElementById('modal-step-1').style.display = 'block';
    document.getElementById('modal-step-2').style.display = 'none';
    document.getElementById('modal-title').textContent = 'Créer un compte';
}