<?php
/**
 * A model class for the RedBean object Form
 *
 * @author Lindsay Marshall <lindsay.marshall@ncl.ac.uk>
 * @copyright 2016 Newcastle University
 *
 */
    namespace Model;
/**
 * A class implementing a RedBean model for Form beans
 */
    class Form extends \RedBeanPHP\SimpleModel
    {
        private static $methods     = ['', 'GET', 'POST'];
        private static $attributes  = ['type', 'class', 'name', 'placeholder'];
        
        private $lcount             = 1;
/**
 * @var array   Key is name of field and the array contains flags for checks
 */
        private static $editfields = [
            'name'            => [TRUE, FALSE],         # [NOTEMPTY, CHECK/RADIO]
            'action'          => [TRUE, FALSE],
            'method'          => [TRUE, FALSE],
            'idval'           => [FALSE, FALSE],
            'class'           => [FALSE, FALSE],
            'multipart'       => [FALSE, TRUE],
        ];
/**
 * @var array The kinds of flags that fields can have
 */
        private static $flags = [
            'checked'       => ['Checked', TRUE, 0x01],
            'disabled'      => ['Disabled', FALSE, 0x02],
            'multiple'      => ['Multiple', TRUE, 0x04],
            'readonly'      => ['Readonly', FALSE, 0x08],
            'required'      => ['Required', FALSE, 0x10],
        ];

        use \ModelExtend\FWEdit;
/**
 * Return the form name
 *
 * @return object
 */
        public function name()
        {
	    return $this->bean->name;
        }
/**
 * Return the form's method
 *
 * @return object
 */
        public function method()
        {
	    return $this->bean->method;
        }
/**
 * Return the form's fields
 *
 * @return object
 */
        public function fields()
        {
	    return $this->bean->with('order by seqn,name')->ownFormfield;
        }
/**
 * Return the form's fields by sequence
 *
 * @return object
 */
        public function sequence()
        {
            $res = [];
            foreach ($this->bean->fields() as $fld)
            {
                $res[$fld->seqn][] = $fld;
            }
	    return $res;
        }
/**
 * Setup for an edit
 *
 * @param object    $context*
 * 
 * @return void
 */
        public function startEdit($context)
        {
            $context->local()->addval('flags', self::$flags);
        }
/**
 * Handle a form edit
 *
 * @param object    $context
 *
 * @return void
 */
        public function edit($context)
        {
            $fdt = $context->formdata();
            $emess = $this->dofields($fdt);

            foreach ($fdt->posta('new') as $ix => $fid)
            {
                if (($type = $fdt->post(['type', $ix], '')) !== '')
                {
                    $fld = \R::dispense('formfield');
                    $fld->type = $type;
                    foreach (['label', 'name', 'class', 'idval', 'placeholder', 'value', 'other', 'seqn'] as $fname)
                    {
                        $fld->$fname = $fdt->post(['fld'.$fname, $ix], '');
                    }
                    $fld->flags = 0;
                    foreach (self::$flags as $fn => $fv)
                    {
                        $fld->flags |= $fdt->post(['fld'.$fn, $ix], 0);
                    }
                    \R::store($fld);
                    $this->bean->xownForm[] = $fld;
                }
            }
            \R::store($this->bean);
            return [!empty($emess), $emess];
        }
/**
 * View a form
 *
 * @return void
 */
        public function view()
        {
        }
/**
 * Handle a label
 *
 * @param object    $fld    The field
 * @param string    $class  The class name
 *
 * @return string   The field idval might be updated also
 */
        private function doLabel($fld, $class = '', $inp = '')
        {
            $label = '';
            if ($fld->label !== '')
            {
                if ($fld->idval == '')
                {
                    $fld->idval = $this->bean->name.$this->lcount;
                    $this->lcount += 1;
                }
                return '<label for="'.$fld->idval.'"'.($class !== '' ? (' class="'.$class.'"') : '').'>'.$inp.$fld->label.'</label>';
            }
            return '';
        }
/**
 * Render a field's attributes
 *
 * @param object    $fld
 * @param string    $class  The class name
 * @param boolean   $doValue  The class name
 *
 * @return string
 */
        private function fieldAttr($fld, $class, $doValue = TRUE)
        {
            $attrs = self::$attributes;
            if ($doValue)
            { # include the value in the attributes
                $attrs[] = 'value';
            }
            $res = ['']; # ensures a space at the start of the result
            if ($fld->idval !== '')
            {
                $res[] = 'id="'.$fld->idval.'"';
            }
            if ($class !== '')
            { # add a standard class
                $fld->class = trim($class.' '.$fld->class);
            }
            foreach ($attrs as $atr)
            {
                if ($fld->$atr !== '')
                {
                    $res[] = $atr.'="'.$fld->$atr.'"';
                }
            }
            foreach (['checked', 'selected', 'required', 'readonly', 'disabled'] as $atr)
            {
                if ($fld->$atr)
                {
                    $res[] = $atr.'="'.$atr.'"';
                }
            }
            return implode(' ', $res);
        }
/**
 * Render a form
 *
 * @return string
 */
        public function render($values = [])
        {
            $this->lcount = 1;
            $form = '<form action="'.
                ($this->bean->action === '' ? '#' : $this->bean->action).'" '.
                ($this->bean->class !== '' ? (' class="'.$this->bean->class.'"') : '').'" '.
                ($this->bean->idval !== '' ? (' id="'.$this->bean->idval.'"') : '').'" '.
                'method="'.self::$methods[$this->bean->method].'"'.
                ($this->multipart ? ' enctype="multipart/form-data"' : '').
                '>';
            foreach ($this->sequence() as $flds)
            {
                $fld = reset($flds);
                switch ($fld->type)
                {
                case 'checkbox':
                case 'radio':
                    $form .= '<div class="form-group"><div class="form-check'.(count($flds) > 1 ? ' form-check-inline' : '').'">';
                    foreach ($flds as $fld)
                    {
                        if (isset($values[$fld->name]) && $fld->value == $values[$fld->name])
                        {
                            $fld->checked = 1;
                        }
                        $input = '<input'.$this->fieldAttr($fld, '', FALSE, $values).'/>';
                        $form .= $this->doLabel($fld, 'form-check-label', $input); # need to do this first as it might set the label field in $fld
                    }
                    $form .= '</div></div>';
                    break;
                //case 'radio':
                //    if (isset($values[$fld->name]) && $fld->value == $values[$fld->name])
                //    {
                //        $fld->checked = 1;
                //    }
                //    $label = $this->doLabel($fld, 'form-check-label'); # need to do this first as it might set the label field in $fld
                //    $form .= '<div class="form-group"><div class="form-check"><input'.$this->fieldAttr($fld, 'form-check-input', FALSE, $values).'/>'.$label.'</div></div>';
                //    break;
                case 'select':
                    $form .= '<div class="form-group">'.$this->doLabel($fld).'<select'.$this->fieldAttr($fld, 'form-control', FALSE).'>';
                    $optgroup = FALSE;
                    foreach ($values[$fld->name] as $option)
                    {
                        if (is_object($option))
                        {
                            if (isset($option->optgroup))
                            {
                                if ($optgroup)
                                { # one open already so close it
                                    $form .= '</optgroup>';
                                }
                                if ($option->optgroup !== '') # If the name is empty then we want to close an open optgroup without startng a new one
                                { 
                                    $form .= '<optgroup label="'.$option->optgroup.'"'.(isset($option->disabled) ? ' disabled="disabled"' : '').'>';
                                    $optgroup == TRUE;
                                }
                            }
                            else
                            {
                                $form .= '<option value="'.$option->value.'">'.$option->text.'</option>';
                            }
                        }
                        elseif (is_array($option))
                        {
                            if ($option[0] === NULL)
                            {
                                if ($optgroup)
                                { # one open already so close it
                                    $form .= '</optgroup>';
                                }
                                if ($option[1] !== NULL) # If the name is also NULL then we want to close an open optgroup without startng a new one
                                { 
                                    $form .= '<optgroup label="'.$option[1].'"'.(isset($option[2]) ? ' disabled="disabled"' : '').'>';
                                    $optgroup == TRUE;
                                }
                            }
                            else
                            {
                                $form .= '<option value="'.$option[0].'">'.$option[1].'</option>';
                            }
                        }
                        else
                        {
                            $form .= '<option value="'.$option.'">'.$option.'</option>';
                        }
                    }
                    if ($optgroup)
                    { # close any open optgroup
                        $form .= '</optgroup>';
                    }
                    $form .= '</select></div>';
                    break;
                case 'textarea':
                    $form .= '<div class="form-group">'.$this->doLabel($fld).'<textarea'.$this->fieldAttr($fld, 'form-control', FALSE).'>'.($values[$fld->name] ?? $this->value).'</textarea></div>';
                    break;
                case 'submit' :
                    $form .= '<div class="form-group"><button type="submit"'.$this->fieldAttr($fld, '', FALSE).'>'.$fld->value.'</button></div>';
                    break;
                case 'button' :
                    $form .= '<div class="form-group"><button'.$this->fieldAttr($fld, '', FALSE).'>'.$fld->value.'</button></div>';
                    break;
                default: # all the other types are very much the same.
                    if (isset($values[$fld->name]))
                    {
                        $fld->value = $values[$fld->name];
                    }
                    $form .= '<div class="form-group">'.$this->doLabel($fld).'<input'.$this->fieldAttr($fld, 'form-control', TRUE, $values).'/></div>';
                    break;
                }
            }
            return $form.'</form>';
        }
/**
 * Add a form
 *
 * @return void
 */
        public static function add($context)
        {
            $fdt = $context->formdata();
            $p = \R::dispense('form');
            $p->name = $fdt->mustpost('name');
            $p->action = $fdt->mustpost('action');
            $p->class = $fdt->mustpost('class');
            $p->idval = $fdt->mustpost('idval');
            $p->method = $fdt->mustpost('method');
            $p->multipart = $fdt->post('multipart', 0);
            echo \R::store($p);
        }
    }
?>
