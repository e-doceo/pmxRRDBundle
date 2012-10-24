<?php
/**
 * Created by JetBrains PhpStorm.
 * @author: pomaxa none <pomaxa@gmail.com>
 * @date: 10/19/12
 */

namespace Pmx\Bundle\RrdBundle\Component;

class DSType
{
    const COUNTER = 'COUNTER';
    /**
     * получаемое значение просто кладется в rrdb
     * (например, для счетчика загрузки CPU или температуры, когда нужна не разность, а само значение)
     */
    const GAUGE = 'GAUGE';
    /**
     * COUNTER, который может уменьшаться (защиты от переполнения нет)
     */
    const DERIVE = 'DERIVE';
    /**
     * получаемое значение делится на интервал времени между отсчетами,
     * полезно для обнуляющихся при чтении источников данных
     */
    const ABSOLUTE = 'ABSOLUTE';
    /**
     * на разбирался. Если кто разбирался - буду признателен за комментарий
     */
    const COMPUTE = 'COMPUTE';
}