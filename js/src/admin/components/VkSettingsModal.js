import SettingsModal from 'flarum/components/SettingsModal';

export default class VkSettingsModal extends SettingsModal {
  className() {
    return 'VkSettingsModal Modal--small';
  }

  title() {
    return app.translator.trans('flarum-auth-vk.admin.vk_settings.title');
  }

  form() {
    return [
      <div className="Form-group">
        <label>{app.translator.trans('flarum-auth-vk.admin.vk_settings.client_id_label')}</label>
        <input className="FormControl" bidi={this.setting('flarum-auth-vk.client_id')}/>
      </div>,

      <div className="Form-group">
        <label>{app.translator.trans('flarum-auth-vk.admin.vk_settings.client_secret_label')}</label>
        <input className="FormControl" bidi={this.setting('flarum-auth-vk.client_secret')}/>
      </div>
    ];
  }
}
