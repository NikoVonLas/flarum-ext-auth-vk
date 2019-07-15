import VkSettingsModal from './components/VkSettingsModal';

app.initializers.add('flarum-auth-vk', () => {
  app.extensionSettings['flarum-auth-vk'] = () => app.modal.show(new VkSettingsModal());
});
