import { extend } from 'flarum/extend';
import app from 'flarum/app';
import LogInButtons from 'flarum/components/LogInButtons';
import LogInButton from 'flarum/components/LogInButton';

app.initializers.add('flarum-auth-vk', () => {
  extend(LogInButtons.prototype, 'items', function(items) {
    items.add('vk',
      <LogInButton
        className="Button LogInButton--vk"
        icon="fab fa-vk"
        path="/auth/vk">
        {app.translator.trans('flarum-auth-vk.forum.log_in.with_vk_button')}
      </LogInButton>
    );
  });
});
