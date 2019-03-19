<?php
#parse("PHP File Header.php")

#if (${NAMESPACE})
namespace ${NAMESPACE};
#end

use EventEngine\Data\ImmutableRecord;
use EventEngine\Data\ImmutableRecordLogic;

final class ${NAME} implements ImmutableRecord 
{
    use ImmutableRecordLogic;
    
    
}
