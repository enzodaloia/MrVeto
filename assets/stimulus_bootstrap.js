// Import du starter Stimulus
import { startStimulusApp } from '@symfony/stimulus-bridge';

// Démarre l'application Stimulus
const app = startStimulusApp();

// Charge automatiquement tous les contrôleurs dans controllers/
app.loadControllers(require.context(
    './controllers',
    true,
    /\.[jt]sx?$/
));

// Export pour pouvoir l’utiliser ailleurs si besoin
export { app };

// Ici tu peux enregistrer des contrôleurs custom 3rd party si nécessaire
// Exemple :
// import SomeController from './some_controller';
// app.register('some-controller', SomeController);
