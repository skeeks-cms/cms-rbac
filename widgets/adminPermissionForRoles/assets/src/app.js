/*!
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 07.02.2017
 */
(function(sx, $, _)
{
    sx.createNamespace('classes', sx);

    sx.classes.PermissionForRoles = sx.classes.Component.extend({

        _init: function()
        {

        },

        getJQueryWrapper: function()
        {
            return $('#' + this.get('id'));
        },

        getJQuerySelect: function()
        {
            return $('select', this.getJQueryWrapper());
        },

        _onDomReady: function()
        {
            var self = this;

            this.getJQuerySelect().on('change', function()
            {
                self.save();
            });
        },

        save: function()
        {
            //sx.block(this.jQueryWrapper);
            var requestRoles = [];

            if (this.get('notClosedRoles', []))
            {
                _.each(this.get('notClosedRoles', []), function(value, key)
                {
                    requestRoles.push(value);
                });
            }

            var ElementValues = this.getJQuerySelect().val();
            if (ElementValues)
            {
                _.each(ElementValues, function(value, key)
                {
                    if (_.indexOf(requestRoles, value) == -1)
                    {
                        requestRoles.push(value);
                    }
                });
            }

            var AjaxQuery = sx.ajax.preparePostQuery(this.get('backend', ''), {
                'permissionName'    : this.get('permissionName'),
                'roles'             : requestRoles
            });

            new sx.classes.AjaxHandlerStandartRespose(AjaxQuery, {
                'blocker'       : new sx.classes.Blocker(this.getJQueryWrapper()),
                'enableBlocker' : true
            });

            new sx.classes.AjaxHandlerNoLoader(AjaxQuery);

            AjaxQuery.execute();
        }
    });
})(sx, sx.$, sx._);