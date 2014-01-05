/**#@+
* Boolean rules bitfield
*/
/** @const */ var RULE_AUTO_CLOSE        =   1;
/** @const */ var RULE_AUTO_REOPEN       =   2;
/** @const */ var RULE_BREAK_PARAGRAPH   =   4;
/** @const */ var RULE_CREATE_PARAGRAPHS =   8;
/** @const */ var RULE_IGNORE_TAGS       =  16;
/** @const */ var RULE_IGNORE_TEXT       =  32;
/** @const */ var RULE_IS_TRANSPARENT    =  64;
/** @const */ var RULE_NO_BR_CHILD       = 128;
/** @const */ var RULE_NO_BR_DESCENDANT  = 256;
/** @const */ var RULE_TRIM_WHITESPACE   = 512;
/**#@-*/

/**
* @const All the characters that are considered whitespace
*/
var WHITESPACE = " \n\t";

/**
* @type {!Logger} This parser's logger
*/
var logger = new Logger;

/**
* @type {!Object} Variables registered for use in filters
*/
var registeredVars;

/**
* @type {!Object} Tags' config
* @const
*/
var tagsConfig;

/**
* @type {!string} Text being parsed
*/
var text;

/**
* @type {!number} Length of the text being parsed
*/
var textLen;

/**
* @type {!number} Counter incremented everytime the parser is reset. Used to as a canary to detect
*                 whether the parser was reset during execution
*/
var uid = 0;

//==========================================================================
// Public API
//==========================================================================

/**
* Disable a tag
*
* @param {!string} tagName Name of the tag
*/
function disableTag(tagName)
{
	if (tagsConfig[tagName])
	{
		tagsConfig[tagName].isDisabled = true;
	}
}

/**
* Enable a tag
*
* @param {!string} tagName Name of the tag
*/
function enableTag(tagName)
{
	if (tagsConfig[tagName])
	{
		tagsConfig[tagName].isDisabled = false;
	}
}

/**
* Get this parser's Logger instance
*
* @return {!Logger}
*/
function getLogger()
{
	return logger;
}

/**
* Parse a text
*
* @param  {!string} _text Text to parse
* @return {!string}       XML representation
*/
function parse(_text)
{
	// Reset the parser and save the uid
	reset(_text);
	var _uid = uid;

	// Do the heavy lifting
	executePluginParsers();
	processTags();

	// Check the uid in case a plugin or a filter reset the parser mid-execution
	if (uid !== _uid)
	{
		throw 'The parser has been reset during execution';
	}

	return output;
}

/**
* Reset the parser for a new parsing
*
* @param {!string} _text Text to be parsed
*/
function reset(_text)
{
	// Normalize CR/CRLF to LF, remove control characters that aren't allowed in XML
	_text = _text.replace(/\r\n?/g, "\n", _text);
	_text = _text.replace(/[\x00-\x08\x0B\x0C\x0E-\x1F]+/g, '', _text);

	// Clear the logs
	logger.clear();

	// Initialize the rest
	currentFixingCost = 0;
	isRich     = false;
	namespaces = {};
	output     = '';
	text       = _text;
	textLen    = text.length;
	tagStack   = [];
	tagStackIsSorted = true;
	wsPos      = 0;

	// Bump the UID
	++uid;
}

/**
* Change a tag's tagLimit
*
* NOTE: the default tagLimit should generally be set during configuration instead
*
* @param {!string} tagName  The tag's name, in UPPERCASE
* @param {!number} tagLimit
*/
function setTagLimit(tagName, tagLimit)
{
	if (tagsConfig[tagName])
	{
		tagsConfig[tagName].tagLimit = tagLimit;
	}
}

/**
* Change a tag's nestingLimit
*
* NOTE: the default nestingLimit should generally be set during configuration instead
*
* @param {!string} tagName      The tag's name, in UPPERCASE
* @param {!number} nestingLimit
*/
function setNestingLimit(tagName, nestingLimit)
{
	if (tagsConfig[tagName])
	{
		tagsConfig[tagName].nestingLimit = nestingLimit;
	}
}