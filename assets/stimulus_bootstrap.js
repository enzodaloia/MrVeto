// Import du starter Stimulus
import { startStimulusApp } from '@symfony/stimulus-bridge';

// Charge automatiquement tous les contrôleurs dans controllers/
export const app = startStimulusApp(require.context(
    './controllers',
    true,
    /\.[jt]sx?$/
));

console.log('Stimulus app started', app);



// Ici tu peux enregistrer des contrôleurs custom 3rd party si nécessaire
// Exemple :
// import SomeController from './some_controller';
// app.register('some-controller', SomeController);
